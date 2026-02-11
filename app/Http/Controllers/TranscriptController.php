<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Interaction;
use App\Models\CampaignUserAssignment;
use App\Jobs\TranscribeAudioJob;
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
        $qualityForms = \App\Models\QualityForm::whereHas('versions', function ($q) {
            $q->where('status', 'published');
        })
            ->get()
            ->map(function ($form) {
                return [
                    'id' => $form->id,
                    'name' => $form->name,
                    'campaign_id' => $form->campaign_id,
                ];
            })
            ->groupBy('campaign_id');

        $agents = \App\Models\User::role('agent')->orderBy('name')->get();
        return view('transcripts.create', compact('campaigns', 'agents', 'qualityForms'));
    }

    public function store(Request $request)
    {
        $audioExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'webm'];
        $textExtensions = ['txt'];
        $allExtensions = implode(',', array_merge($textExtensions, $audioExtensions));

        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'quality_form_id' => 'nullable|exists:quality_forms,id',
            'agent_id' => 'required|exists:users,id',
            'occurred_at' => 'required|date',
            'transcript_files' => 'required|array|min:1|max:50',
            'transcript_files.*' => "required|file|extensions:{$allExtensions}|max:25600",
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
        $audioCount = 0;

        foreach ($validated['transcript_files'] as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $isAudio = in_array($extension, $audioExtensions);

            $storagePath = $isAudio ? 'audios' : 'transcripts';
            $path = $file->store($storagePath, 'local');

            $interactionData = [
                'campaign_id' => $validated['campaign_id'],
                'quality_form_id' => $validated['quality_form_id'] ?? null,
                'agent_id' => $validated['agent_id'],
                'supervisor_id' => $assignment->supervisor_id,
                'occurred_at' => $validated['occurred_at'],
                'uploaded_by' => auth()->id(),
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'source_type' => $isAudio ? 'audio' : 'text',
                'batch_id' => $batchId,
            ];

            if ($isAudio) {
                $interactionData['transcript_text'] = '';
                $interactionData['transcription_status'] = 'pending';
                $interactionData['status'] = 'uploaded';
            } else {
                $interactionData['transcript_text'] = file_get_contents($file->getRealPath());
                $interactionData['status'] = 'uploaded';
            }

            $interaction = Interaction::create($interactionData);

            // Dispatch transcription job for audio files
            if ($isAudio) {
                TranscribeAudioJob::dispatch($interaction->id);
                $audioCount++;
            }

            $uploaded++;
        }

        $message = "{$uploaded} archivo(s) cargado(s) exitosamente.";
        if ($audioCount > 0) {
            $message .= " {$audioCount} audio(s) en proceso de transcripción.";
        }

        return redirect()->route('transcripts.index')
            ->with('success', $message);
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

    public function audio(Interaction $interaction)
    {
        if (!$interaction->isAudio()) {
            abort(404, 'This interaction does not have an audio file.');
        }

        $path = Storage::disk('local')->path($interaction->file_path);

        if (!file_exists($path)) {
            abort(404, 'Audio file not found.');
        }

        $mimeTypes = [
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'm4a' => 'audio/mp4',
            'webm' => 'audio/webm',
        ];

        $extension = strtolower(pathinfo($interaction->file_name, PATHINFO_EXTENSION));
        $mime = $mimeTypes[$extension] ?? 'audio/mpeg';

        return response()->file($path, [
            'Content-Type' => $mime,
        ]);
    }

    public function evaluate(Interaction $interaction)
    {
        // Verificar que no tenga ya una evaluación
        if ($interaction->evaluation) {
            return back()->with('error', 'Esta transcripción ya fue evaluada.');
        }

        // Delegate form resolution to AIEvaluationService which has robust fallback logic
        $aiService = app(\App\Services\AIEvaluationService::class);
        $evaluation = $aiService->evaluateInteraction($interaction);

        if ($evaluation) {
            $interaction->update(['status' => 'scored']);
            return redirect()->route('evaluations.show', $evaluation)
                ->with('success', 'Evaluación completada exitosamente.');
        }

        return back()->with('error', 'No se pudo realizar la evaluación. Verifique los logs o la configuración de la ficha de calidad.');
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
