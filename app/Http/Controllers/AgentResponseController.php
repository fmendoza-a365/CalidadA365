<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\AgentResponse;
use App\Models\DisputeResolution;
use Illuminate\Http\Request;

class AgentResponseController extends Controller
{
    public function store(Request $request, Evaluation $evaluation)
    {
        $this->authorize('respond', $evaluation);

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

        if ($validated['response_type'] === 'dispute') {
            DisputeResolution::create([
                'agent_response_id' => $response->id,
                'evaluation_id' => $evaluation->id,
            ]);


            $evaluation->update(['status' => 'disputed']);
            
            // Notificar a QA Managers y al Supervisor asignado (si existiera lógica directa, por ahora a QA Managers)
            $qaManagers = \App\Models\User::role('qa_manager')->get();
            foreach ($qaManagers as $manager) {
                $manager->notify(new \App\Notifications\DisputeOpened($evaluation, auth()->user()));
            }

        } else {
            $evaluation->update(['status' => 'agent_responded']);
        }

        return redirect()->route('evaluations.show', $evaluation)
            ->with('success', 'Respuesta registrada exitosamente.');
    }

    public function resolve(Request $request, DisputeResolution $dispute)
    {
        $this->authorize('resolve', $dispute);

        $validated = $request->validate([
            'resolution_decision' => 'required|in:upheld,overturned,partial',
            'resolution_notes' => 'required|string',
            'adjusted_score' => 'nullable|numeric|min:0|max:100',
        ]);

        $dispute->update([
            'resolved_by' => auth()->id(),
            'resolution_decision' => $validated['resolution_decision'],
            'resolution_notes' => $validated['resolution_notes'],
            'adjusted_score' => $validated['adjusted_score'] ?? null,
            'resolved_at' => now(),
        ]);

        $dispute->evaluation->update(['status' => 'resolved']);

        if ($validated['adjusted_score']) {
            $dispute->evaluation->update(['percentage_score' => $validated['adjusted_score']]);
        }
        
        // Notificar al agente
        if ($dispute->evaluation->agent) {
            $dispute->evaluation->agent->notify(new \App\Notifications\DisputeResolved($dispute->evaluation, $dispute));
        }

        return redirect()->route('evaluations.show', $dispute->evaluation)
            ->with('success', 'Disputa resuelta exitosamente.');
    }
}
