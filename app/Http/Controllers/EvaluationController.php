<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
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

        return view('evaluations.index', compact('evaluations'));
    }

    public function show(Evaluation $evaluation)
    {
        $this->authorize('view', $evaluation);

        $evaluation->load([
            'interaction',
            'formVersion.attributes.subAttributes',
            'items.subAttribute.attribute', // Ensure relationships are correct in models
            'agentResponse',
            'dispute'
        ]);

        // Marcar como vista por el asesor
        if (auth()->user()->hasRole('agent') && !$evaluation->agent_viewed_at) {
            $evaluation->update(['agent_viewed_at' => now()]);
        }

        return view('evaluations.show', compact('evaluation'));
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
