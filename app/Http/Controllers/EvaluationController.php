<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\Campaign;
use App\Jobs\ScoreTranscriptJob;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Evaluation::with(['agent', 'campaign', 'interaction'])
            ->forUser($user);

        if ($request->campaign_id) {
            $query->where('campaign_id', $request->campaign_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $evaluations = $query->latest()->paginate(20);
        $campaigns = Campaign::active()->forUser($user)->orderBy('name')->get();
        $statusOptions = [
            Evaluation::STATUS_PENDING_MONITOR_REVIEW,
            Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
            Evaluation::STATUS_PUBLISHED_TO_AGENT,
            Evaluation::STATUS_AGENT_ACCEPTED,
            Evaluation::STATUS_AGENT_DISPUTED,
            Evaluation::STATUS_DISPUTE_RESOLVED,
        ];

        return view('evaluations.index', compact('evaluations', 'campaigns', 'statusOptions'));
    }

    public function show(Evaluation $evaluation)
    {
        $this->authorize('view', $evaluation);

        $evaluation->load([
            'interaction',
            'formVersion.attributes.subAttributes',
            'items.subAttribute.attribute', // Ensure relationships are correct in models
            'agentResponse',
            'dispute.supervisorReviewer',
            'dispute.qaReviewer',
            'dispute.coordinatorReviewer',
            'dispute.resolvedBy',
            'reviewer',
            'publisher',
            'evaluator',
        ]);

        // Marcar como vista por el asesor
        if (auth()->user()->hasRole('agent') && $evaluation->isVisibleToAgent() && !$evaluation->agent_viewed_at) {
            $evaluation->update(['agent_viewed_at' => now()]);
        }

        return view('evaluations.show', compact('evaluation'));
    }

    public function publish(Request $request, Evaluation $evaluation)
    {
        $this->authorize('publish', $evaluation);

        $validated = $request->validate([
            'review_notes' => 'nullable|string|max:5000',
        ]);

        if (!$evaluation->canBePublished()) {
            return back()->with('error', 'Esta evaluación no está en un estado publicable.');
        }

        $evaluation->update([
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'] ?? null,
            'published_by' => auth()->id(),
            'visible_to_agent_at' => now(),
            'finalized_at' => now(),
            'evaluator_id' => $evaluation->evaluator_id ?: auth()->id(),
        ]);

        if ($evaluation->agent) {
            $evaluation->agent->notify(new \App\Notifications\EvaluationCompleted($evaluation));
        }

        return redirect()->route('evaluations.show', $evaluation)
            ->with('success', 'Evaluación aprobada y publicada al asesor.');
    }

    public function reanalyze(Evaluation $evaluation)
    {
        $this->authorize('reanalyze', $evaluation);

        if ($evaluation->type !== 'ai') {
            return back()->with('error', 'Solo se puede reanalizar una evaluación IA.');
        }

        if ($evaluation->isVisibleToAgent()) {
            return back()->with('error', 'No se puede reanalizar una evaluación ya publicada al asesor.');
        }

        $evaluation->update([
            'status' => Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
            'reanalysis_requested_at' => now(),
            'reanalysis_requested_by' => auth()->id(),
        ]);

        $evaluation->interaction->update(['status' => 'queued']);
        ScoreTranscriptJob::dispatch($evaluation->interaction_id)->onQueue('ai-scoring');

        return redirect()->route('evaluations.show', $evaluation)
            ->with('success', 'Reanálisis IA enviado a cola.');
    }

    public function toggleGold(Evaluation $evaluation)
    {
        if (!auth()->user()->hasAnyRole(['admin', 'qa_manager'])) {
            abort(403);
        }

        $evaluation->is_gold = !$evaluation->is_gold;
        $evaluation->save();

        $message = $evaluation->is_gold
            ? 'Evaluación marcada como Golden Record (Referencia para IA).'
            : 'Evaluación desmarcada como Golden Record.';

        return back()->with('success', $message);
    }
}
