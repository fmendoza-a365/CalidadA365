<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Interaction;
use App\Models\CampaignUserAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TranscriptController extends Controller
{
    public function index(Request $request)
    {
        $query = Interaction::with(['campaign', 'agent', 'supervisor']);

        if ($request->campaign_id) {
            $query->where('campaign_id', $request->campaign_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $interactions = $query->latest('occurred_at')->paginate(20);
        $campaigns = Campaign::active()->get();

        return view('transcripts.index', compact('interactions', 'campaigns'));
    }

    public function create()
    {
        $campaigns = Campaign::active()->get();
        // Fetch active quality forms grouped by campaign for the frontend
        $qualityForms = \App\Models\QualityForm::whereHas('versions', function($q) {
            $q->where('status', 'published');
        })->get()->groupBy('campaign_id');
        
        $agents = \App\Models\User::role('agent')->orderBy('name')->get();
        return view('transcripts.create', compact('campaigns', 'agents', 'qualityForms'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'quality_form_id' => 'nullable|exists:quality_forms,id',
            'agent_id' => 'required|exists:users,id',
            'occurred_at' => 'required|date',
            'transcript_files' => 'required|array|min:1|max:50',
            'transcript_files.*' => 'required|file|extensions:txt|max:5120',
        ]);

        // Obtener supervisor de asignación activa
        $assignment = CampaignUserAssignment::where([
            'campaign_id' => $validated['campaign_id'],
            'agent_id' => $validated['agent_id'],
            'is_active' => true,
        ])->first();

        if (!$assignment) {
            return back()->withErrors(['agent_id' => 'El asesor no está asignado a esta campaña.']);
        }

        $batchId = count($validated['transcript_files']) > 1 ? Str::uuid() : null;
        $uploaded = 0;

        foreach ($validated['transcript_files'] as $file) {
            $path = $file->store('transcripts', 'local');
            $text = file_get_contents($file->getRealPath());

            $interaction = Interaction::create([
                'campaign_id' => $validated['campaign_id'],
                'quality_form_id' => $validated['quality_form_id'] ?? null,
                'agent_id' => $validated['agent_id'],
                'supervisor_id' => $assignment->supervisor_id,
                'occurred_at' => $validated['occurred_at'],
                'uploaded_by' => auth()->id(),
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'transcript_text' => $text,
                'status' => 'uploaded',
                'batch_id' => $batchId,
            ]);

            // La evaluación se disparará manualmente después
            // $interaction->update(['status' => 'queued']);
            // \App\Jobs\ScoreTranscriptJob::dispatch($interaction->id);

            $uploaded++;
        }

        return redirect()->route('transcripts.index')
            ->with('success', "{$uploaded} transcripción(es) cargada(s) exitosamente.");
    }

    public function show(Interaction $interaction)
    {
        $interaction->load(['campaign', 'agent', 'supervisor', 'evaluation.items.subAttribute']);
        return view('transcripts.show', compact('interaction'));
    }

    public function download(Interaction $interaction)
    {
        return Storage::disk('local')->download($interaction->file_path, $interaction->file_name);
    }

    public function evaluate(Interaction $interaction)
    {
        // Verificar que no tenga ya una evaluación
        if ($interaction->evaluation) {
            return back()->with('error', 'Esta transcripción ya fue evaluada.');
        }

        // Verificar si la interacción tiene una ficha asignada explícitamente
        // O si la campaña tiene una ficha activa
        $formVersion = null;
        
        if ($interaction->qualityForm) {
             $formVersion = $interaction->qualityForm->activeVersion;
        } else {
             $formVersion = $interaction->campaign->activeFormVersion;
        }

        if (!$formVersion) {
            return back()->with('error', 'No se encontró una ficha de calidad activa para evaluar esta llamada.');
        }

        // Evaluar usando el servicio de IA
        $aiService = app(\App\Services\AIEvaluationService::class);
        $evaluation = $aiService->evaluateInteraction($interaction);

        if ($evaluation) {
            $interaction->update(['status' => 'scored']);
            return redirect()->route('evaluations.show', $evaluation)
                ->with('success', 'Evaluación completada exitosamente.');
        }

        return back()->with('error', 'Error al procesar la evaluación.');
    }

    public function edit(Interaction $interaction)
    {
        $campaigns = Campaign::active()->get();
        $agents = \App\Models\User::role('agent')->orderBy('name')->get();
        return view('transcripts.edit', compact('interaction', 'campaigns', 'agents'));
    }

    public function update(Request $request, Interaction $interaction)
    {
        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'agent_id' => 'required|exists:users,id',
            'occurred_at' => 'required|date',
            'transcript_text' => 'nullable|string',
        ]);

        // Obtener supervisor de asignación activa
        $assignment = CampaignUserAssignment::where([
            'campaign_id' => $validated['campaign_id'],
            'agent_id' => $validated['agent_id'],
            'is_active' => true,
        ])->first();

        if (!$assignment) {
            return back()->withErrors(['agent_id' => 'El asesor no está asignado a esta campaña.']);
        }

        $interaction->update([
            'campaign_id' => $validated['campaign_id'],
            'agent_id' => $validated['agent_id'],
            'supervisor_id' => $assignment->supervisor_id,
            'occurred_at' => $validated['occurred_at'],
            'transcript_text' => $validated['transcript_text'] ?? $interaction->transcript_text,
        ]);

        return redirect()->route('transcripts.show', $interaction)
            ->with('success', 'Transcripción actualizada exitosamente.');
    }

    public function destroy(Interaction $interaction)
    {
        // Eliminar archivo físico si existe
        if ($interaction->file_path && Storage::disk('local')->exists($interaction->file_path)) {
            Storage::disk('local')->delete($interaction->file_path);
        }

        // Eliminar evaluación asociada si existe
        if ($interaction->evaluation) {
            $interaction->evaluation->items()->delete();
            $interaction->evaluation->delete();
        }

        $interaction->delete();

        return redirect()->route('transcripts.index')
            ->with('success', 'Transcripción eliminada exitosamente.');
    }
}
