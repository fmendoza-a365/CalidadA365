<?php

namespace App\Http\Controllers;

use App\Models\AgentResponse;
use App\Models\DisputeResolution;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class AgentResponseController extends Controller
{
    public function store(Request $request, Evaluation $evaluation)
    {
        $this->authorize('respond', $evaluation);

        if ($evaluation->agentResponse()->exists()) {
            return back()->with('error', 'Ya registraste una respuesta para esta evaluación.');
        }

        $validated = $request->validate([
            'response_type' => 'required|in:accept,dispute',
            'commitment_comment' => 'required_if:response_type,accept|nullable|string',
            'dispute_reason' => 'required_if:response_type,dispute|nullable|string',
            'disputed_items' => 'nullable|array',
        ]);

        $response = AgentResponse::create([
            'evaluation_id' => $evaluation->id,
            'agent_id' => auth()->id(),
            'response_type' => $validated['response_type'],
            'commitment_comment' => $validated['commitment_comment'] ?? null,
            'dispute_reason' => $validated['dispute_reason'] ?? null,
            'disputed_items' => $validated['disputed_items'] ?? null,
        ]);

        $fromStatus = $evaluation->status;

        if ($validated['response_type'] === 'dispute') {
            $dispute = DisputeResolution::create([
                'agent_response_id' => $response->id,
                'evaluation_id' => $evaluation->id,
                'status' => DisputeResolution::STATUS_PENDING_SUPERVISOR_REVIEW,
            ]);

            $evaluation->update(['status' => Evaluation::STATUS_AGENT_DISPUTED]);

            $evaluation->recordAuditEvent('agent_disputed', auth()->user(), [
                'agent_response_id' => $response->id,
                'dispute_id' => $dispute->id,
                'disputed_items_count' => count($validated['disputed_items'] ?? []),
            ], $fromStatus, Evaluation::STATUS_AGENT_DISPUTED);

            if ($evaluation->interaction?->supervisor) {
                $evaluation->interaction->supervisor->notify(new \App\Notifications\DisputeOpened($evaluation, auth()->user()));
            }

            $qaManagers = collect();
            if (Role::where('name', 'qa_manager')->where('guard_name', 'web')->exists()) {
                $qaManagers = User::role('qa_manager')->get();
            }
            foreach ($qaManagers as $manager) {
                $manager->notify(new \App\Notifications\DisputeOpened($evaluation, auth()->user()));
            }

        } else {
            $evaluation->update(['status' => Evaluation::STATUS_AGENT_ACCEPTED]);

            $evaluation->recordAuditEvent('agent_accepted', auth()->user(), [
                'agent_response_id' => $response->id,
                'commitment_present' => filled($validated['commitment_comment'] ?? null),
            ], $fromStatus, Evaluation::STATUS_AGENT_ACCEPTED);
        }

        return redirect()->route('evaluations.show', $evaluation)
            ->with('success', 'Respuesta registrada exitosamente.');
    }

    public function supervisorReview(Request $request, DisputeResolution $dispute)
    {
        $this->authorize('supervisorReview', $dispute);

        $validated = $request->validate([
            'supervisor_notes' => 'required|string|max:5000',
        ]);

        $fromDisputeStatus = $dispute->status;

        $dispute->update([
            'supervisor_reviewed_by' => auth()->id(),
            'supervisor_reviewed_at' => now(),
            'supervisor_notes' => $validated['supervisor_notes'],
            'status' => DisputeResolution::STATUS_PENDING_QA_REVIEW,
        ]);

        $dispute->evaluation->recordAuditEvent('dispute_supervisor_reviewed', auth()->user(), [
            'dispute_id' => $dispute->id,
            'from_dispute_status' => $fromDisputeStatus,
            'to_dispute_status' => DisputeResolution::STATUS_PENDING_QA_REVIEW,
        ]);

        return redirect()->route('evaluations.show', $dispute->evaluation)
            ->with('success', 'Comentario operativo registrado. La disputa queda pendiente de revisión QA.');
    }

    public function qaReview(Request $request, DisputeResolution $dispute)
    {
        $this->authorize('qaReview', $dispute);

        $validated = $request->validate([
            'qa_recommendation' => 'required|in:upheld,overturned,partial,needs_manager',
            'qa_notes' => 'required|string|max:5000',
        ]);

        $fromDisputeStatus = $dispute->status;

        $dispute->update([
            'qa_reviewed_by' => auth()->id(),
            'qa_reviewed_at' => now(),
            'qa_recommendation' => $validated['qa_recommendation'],
            'qa_notes' => $validated['qa_notes'],
            'status' => DisputeResolution::STATUS_PENDING_COORDINATOR_REVIEW,
        ]);

        $dispute->evaluation->recordAuditEvent('dispute_qa_reviewed', auth()->user(), [
            'dispute_id' => $dispute->id,
            'recommendation' => $validated['qa_recommendation'],
            'from_dispute_status' => $fromDisputeStatus,
            'to_dispute_status' => DisputeResolution::STATUS_PENDING_COORDINATOR_REVIEW,
        ]);

        return redirect()->route('evaluations.show', $dispute->evaluation)
            ->with('success', 'Revisión QA registrada. La disputa queda pendiente de validación del coordinador.');
    }

    public function coordinatorReview(Request $request, DisputeResolution $dispute)
    {
        $this->authorize('coordinatorReview', $dispute);

        $validated = $request->validate([
            'coordinator_decision' => 'required|in:validated,needs_adjustment,escalate_manager',
            'coordinator_notes' => 'required|string|max:5000',
        ]);

        $fromDisputeStatus = $dispute->status;

        $dispute->update([
            'coordinator_reviewed_by' => auth()->id(),
            'coordinator_reviewed_at' => now(),
            'coordinator_decision' => $validated['coordinator_decision'],
            'coordinator_notes' => $validated['coordinator_notes'],
            'status' => DisputeResolution::STATUS_READY_MANAGER_RESOLUTION,
        ]);

        $dispute->evaluation->recordAuditEvent('dispute_coordinator_reviewed', auth()->user(), [
            'dispute_id' => $dispute->id,
            'decision' => $validated['coordinator_decision'],
            'from_dispute_status' => $fromDisputeStatus,
            'to_dispute_status' => DisputeResolution::STATUS_READY_MANAGER_RESOLUTION,
        ]);

        return redirect()->route('evaluations.show', $dispute->evaluation)
            ->with('success', 'Validación del coordinador registrada. La disputa queda lista para resolución final.');
    }

    public function resolve(Request $request, DisputeResolution $dispute)
    {
        $this->authorize('resolve', $dispute);

        $validated = $request->validate([
            'resolution_decision' => 'required|in:upheld,overturned,partial',
            'resolution_notes' => 'required|string',
            'adjusted_score' => 'nullable|numeric|min:0|max:100',
        ]);

        $fromDisputeStatus = $dispute->status;
        $evaluation = $dispute->evaluation;
        $fromEvaluationStatus = $evaluation->status;
        $previousScore = $evaluation->percentage_score;

        $dispute->update([
            'resolved_by' => auth()->id(),
            'resolution_decision' => $validated['resolution_decision'],
            'resolution_notes' => $validated['resolution_notes'],
            'adjusted_score' => $validated['adjusted_score'] ?? null,
            'resolved_at' => now(),
            'status' => DisputeResolution::STATUS_RESOLVED,
        ]);

        $evaluationUpdates = ['status' => Evaluation::STATUS_DISPUTE_RESOLVED];

        if (array_key_exists('adjusted_score', $validated) && $validated['adjusted_score'] !== null) {
            $evaluationUpdates['percentage_score'] = $validated['adjusted_score'];
        }

        $evaluation->update($evaluationUpdates);

        $evaluation->recordAuditEvent('dispute_resolved', auth()->user(), [
            'dispute_id' => $dispute->id,
            'decision' => $validated['resolution_decision'],
            'from_dispute_status' => $fromDisputeStatus,
            'to_dispute_status' => DisputeResolution::STATUS_RESOLVED,
            'previous_score' => $previousScore,
            'adjusted_score' => $validated['adjusted_score'] ?? null,
        ], $fromEvaluationStatus, Evaluation::STATUS_DISPUTE_RESOLVED);

        // Notificar al agente
        if ($evaluation->agent) {
            $evaluation->agent->notify(new \App\Notifications\DisputeResolved($evaluation, $dispute));
        }

        return redirect()->route('evaluations.show', $evaluation)
            ->with('success', 'Disputa resuelta exitosamente.');
    }
}
