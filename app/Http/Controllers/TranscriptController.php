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
        $user = auth()->user();
        $query = Interaction::with(['campaign', 'agent', 'supervisor'])
            ->forUser($user);

        if ($request->campaign_id) {
            $query->where('campaign_id', $request->campaign_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $interactions = $query->latest('occurred_at')->paginate(20);
        $campaigns = Campaign::active()->forUser($user)->get();

        return view('transcripts.index', compact('interactions', 'campaigns'));
    }

    public function create()
    {
        $user = auth()->user();
        $campaigns = Campaign::active()->forUser($user)->get();
        $campaignIds = $campaigns->pluck('id');

        $qualityForms = \App\Models\QualityForm::whereIn('campaign_id', $campaignIds)
            ->whereHas('versions', function ($q) {
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

        // Agents list needs to be filtered by assignment.
        // Also provide all agents for fallback or non-javascript users
        $assignmentsQuery = \App\Models\CampaignUserAssignment::with('agent')
            ->whereIn('campaign_id', $campaignIds)
            ->where('is_active', true);

        if ($user->hasRole('supervisor')) {
            $assignmentsQuery->where('supervisor_id', $user->id);
            $agentIds = $assignmentsQuery->pluck('agent_id');
            $agents = \App\Models\User::whereIn('id', $agentIds)->orderBy('name')->get();
        } elseif ($user->hasRole('agent')) {
            $agents = collect([$user]);
            $assignmentsQuery->where('agent_id', $user->id);
        } else {
            $agents = \App\Models\User::role('agent')->orderBy('name')->get();
        }

        $assignments = $assignmentsQuery->get();
        $agentsByCampaign = $assignments->groupBy('campaign_id')->map(function ($assignedAgents) {
            return $assignedAgents->map(function ($assignment) {
                return [
                    'id' => $assignment->agent->id,
                    'name' => $assignment->agent->name,
                ];
            })->unique('id')->values();
        });

        return view('transcripts.create', compact('campaigns', 'agents', 'qualityForms', 'agentsByCampaign'));
    }

    public function store(Request $request)
    {
        $lock = \Illuminate\Support\Facades\Cache::lock('upload_transcript_' . auth()->id(), 30);

        if (!$lock->get()) {
            return back()->with('info', 'Ya hay una carga en proceso. Por favor espere.');
        }

        try {
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
        } finally {
            $lock->release();
        }
    }

    public function show(Interaction $interaction)
    {
        $user = auth()->user();
        if (!Interaction::forUser($user)->where('id', $interaction->id)->exists()) {
            abort(403, 'No tiene permiso para ver esta transcripción.');
        }

        $interaction->load(['campaign', 'agent', 'supervisor', 'evaluation.items.subAttribute']);
        return view('transcripts.show', compact('interaction'));
    }

    public function download(Interaction $interaction)
    {
        $user = auth()->user();
        if (!Interaction::forUser($user)->where('id', $interaction->id)->exists()) {
            abort(403, 'No tiene permiso para descargar esta transcripción.');
        }

        return Storage::disk('local')->download($interaction->file_path, $interaction->file_name);
    }

    public function audio(Interaction $interaction)
    {
        $user = auth()->user();
        if (!Interaction::forUser($user)->where('id', $interaction->id)->exists()) {
            abort(403, 'No tiene permiso para escuchar esta transcripción.');
        }

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
        $user = auth()->user();
        if (!Interaction::forUser($user)->where('id', $interaction->id)->exists()) {
            abort(403, 'No tiene permiso para evaluar esta transcripción.');
        }

        $lock = \Illuminate\Support\Facades\Cache::lock('evaluate_interaction_' . $interaction->id, 60);

        if (!$lock->get()) {
            return back()->with('info', 'La evaluación ya está en proceso.');
        }

        try {
            // Verificar que no tenga ya una evaluación
            if ($interaction->evaluation()->exists()) {
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
        } finally {
            $lock->release();
        }
    }

    public function edit(Interaction $interaction)
    {
        $user = auth()->user();
        if (!Interaction::forUser($user)->where('id', $interaction->id)->exists()) {
            abort(403, 'No tiene permiso para editar esta transcripción.');
        }

        $campaigns = Campaign::active()->forUser($user)->get();
        // Similarly filter agents
        if ($user->hasRole('supervisor')) {
            $agentIds = \App\Models\CampaignUserAssignment::where('supervisor_id', $user->id)
                ->where('is_active', true)
                ->pluck('agent_id');
            $agents = \App\Models\User::whereIn('id', $agentIds)->orderBy('name')->get();
        } elseif ($user->hasRole('agent')) {
            $agents = collect([$user]);
        } else {
            $agents = \App\Models\User::role('agent')->orderBy('name')->get();
        }

        return view('transcripts.edit', compact('interaction', 'campaigns', 'agents'));
    }

    public function update(Request $request, Interaction $interaction)
    {
        $user = auth()->user();
        if (!Interaction::forUser($user)->where('id', $interaction->id)->exists()) {
            abort(403, 'No tiene permiso para actualizar esta transcripción.');
        }

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
        $user = auth()->user();
        // Typically only admin/managers can delete, but let's at least enforce they can see it
        if (!Interaction::forUser($user)->where('id', $interaction->id)->exists()) {
            abort(403, 'No tiene permiso para eliminar esta transcripción.');
        }

        // Prevent agents from deleting
        if ($user->hasRole('agent')) {
            abort(403, 'Los asesores no pueden eliminar transcripciones.');
        }

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
