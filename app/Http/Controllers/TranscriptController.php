<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Interaction;
use App\Models\CampaignUserAssignment;
use App\Jobs\TranscribeAudioJob;
use App\Jobs\ScoreTranscriptJob;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TranscriptController extends Controller
{
    private const AUDIO_EXTENSIONS = ['mp3', 'wav', 'ogg', 'oga', 'opus', 'm4a', 'mp4', 'mpeg', 'mpga', 'aac', 'webm', 'flac'];
    private const TEXT_EXTENSIONS = ['txt'];
    private const UPLOAD_MAX_KB = 102400;
    private const ALLOWED_MIME_TYPES = [
        'text/plain',
        'audio/aac',
        'audio/flac',
        'audio/mp4',
        'audio/mpeg',
        'audio/mp3',
        'audio/x-mpeg',
        'audio/ogg',
        'audio/opus',
        'audio/x-opus+ogg',
        'audio/vnd.wave',
        'audio/wav',
        'audio/webm',
        'audio/x-aac',
        'audio/x-flac',
        'audio/x-m4a',
        'audio/x-wav',
        'application/mp4',
        'application/ogg',
        'video/mp4',
        'video/webm',
    ];

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
        $user = auth()->user();
        $lock = \Illuminate\Support\Facades\Cache::lock('upload_transcript_' . auth()->id(), 30);

        if (!$lock->get()) {
            return back()->with('info', 'Ya hay una carga en proceso. Por favor espere.');
        }

        try {
            foreach (Arr::wrap($request->file('transcript_files', [])) as $index => $file) {
                if ($file instanceof UploadedFile && !$file->isValid()) {
                    $message = $this->uploadErrorMessage($file);

                    Log::warning('Transcript upload failed before validation', [
                        'index' => $index,
                        'original_name' => $file->getClientOriginalName(),
                        'error_code' => $file->getError(),
                        'upload_max_filesize' => ini_get('upload_max_filesize'),
                        'post_max_size' => ini_get('post_max_size'),
                        'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
                    ]);

                    return back()->withErrors([
                        "transcript_files.{$index}" => $message,
                    ])->withInput();
                }
            }

            $allExtensions = implode(',', array_merge(self::TEXT_EXTENSIONS, self::AUDIO_EXTENSIONS));
            $allowedMimeTypes = implode(',', self::ALLOWED_MIME_TYPES);

            $validated = $request->validate([
                'campaign_id' => 'required|exists:campaigns,id',
                'quality_form_id' => 'nullable|exists:quality_forms,id',
                'agent_id' => 'required|exists:users,id',
                'occurred_at' => 'required|date',
                'transcript_files' => 'required|array|min:1|max:50',
                'transcript_files.*' => "required|file|extensions:{$allExtensions}|mimetypes:{$allowedMimeTypes}|max:" . self::UPLOAD_MAX_KB,
            ], [
                'transcript_files.*.uploaded' => 'El archivo no pudo subirse. Verifique que pese menos de 100 MB y que PHP permita cargas de al menos 100 MB.',
                'transcript_files.*.extensions' => 'Formato no permitido. Use TXT o audio: ' . implode(', ', self::AUDIO_EXTENSIONS) . '.',
                'transcript_files.*.mimetypes' => 'El archivo no parece ser un TXT o audio válido. Si el audio viene de WhatsApp, súbalo como .opus, .ogg o .m4a.',
                'transcript_files.*.max' => 'Cada archivo puede pesar hasta 100 MB.',
            ]);

            if (!Campaign::forUser($user)->whereKey($validated['campaign_id'])->exists()) {
                abort(403, 'No tiene permiso para cargar audios en esta campaña.');
            }

            if (!empty($validated['quality_form_id'])) {
                $formIsValid = \App\Models\QualityForm::whereKey($validated['quality_form_id'])
                    ->where('campaign_id', $validated['campaign_id'])
                    ->whereHas('versions', function ($q) {
                        $q->where('status', 'published');
                    })
                    ->exists();

                if (!$formIsValid) {
                    return back()->withErrors([
                        'quality_form_id' => 'La ficha seleccionada no pertenece a esta campaña o no está publicada.',
                    ])->withInput();
                }
            }

            // Obtener supervisor de asignación activa
            $assignment = $this->visibleAssignmentForUpload($user, (int) $validated['campaign_id'], (int) $validated['agent_id']);

            if (!$assignment) {
                return back()->withErrors(['agent_id' => 'El asesor no está asignado a esta campaña.']);
            }

            $batchId = count($validated['transcript_files']) > 1 ? Str::uuid() : null;
            $uploaded = 0;
            $audioCount = 0;
            $scoringCount = 0;

            foreach ($validated['transcript_files'] as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                $isAudio = in_array($extension, self::AUDIO_EXTENSIONS, true);

                $storagePath = $isAudio ? 'audios' : 'transcripts';
                $path = $file->store($storagePath, $this->privateDisk());

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
                    TranscribeAudioJob::dispatch($interaction->id)->onQueue('transcription');
                    $audioCount++;
                } elseif ($interaction->hasScorableQualityForm()) {
                    $interaction->update(['status' => 'queued']);
                    ScoreTranscriptJob::dispatch($interaction->id)->onQueue('ai-scoring');
                    $scoringCount++;
                }

                $uploaded++;
            }

            $message = "{$uploaded} archivo(s) cargado(s) exitosamente.";
            if ($audioCount > 0) {
                $message .= " {$audioCount} audio(s) en proceso de transcripción y evaluación IA posterior.";
            }
            if ($scoringCount > 0) {
                $message .= " {$scoringCount} transcripción(es) enviada(s) a evaluación IA.";
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

        return Storage::disk($this->privateDisk())->download($interaction->file_path, $interaction->file_name);
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

        $disk = Storage::disk($this->privateDisk());

        if (!$disk->exists($interaction->file_path)) {
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

        $stream = $disk->readStream($interaction->file_path);
        if ($stream === false) {
            abort(404, 'Audio file not found.');
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($interaction->file_name) . '"',
        ]);
    }

    public function evaluate(Interaction $interaction)
    {
        $user = auth()->user();
        if (!Interaction::forUser($user)->where('id', $interaction->id)->exists()) {
            abort(403, 'No tiene permiso para evaluar esta transcripción.');
        }

        if (!$user->can('create_evaluations') && !$user->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator'])) {
            abort(403, 'No tiene permiso para iniciar evaluaciones IA.');
        }

        if ($interaction->isTranscribing()) {
            return back()->with('info', 'La transcripción del audio aún está en proceso.');
        }

        $lock = \Illuminate\Support\Facades\Cache::lock('evaluate_interaction_' . $interaction->id, 60);

        if (!$lock->get()) {
            return back()->with('info', 'La evaluación ya está en proceso.');
        }

        try {
            if ($interaction->manualEvaluation()->exists()) {
                return back()->with('error', 'Esta interacción ya tiene una evaluación final manual.');
            }

            if ($interaction->aiEvaluation()->exists()) {
                return back()->with('error', 'Esta interacción ya tiene una evaluación IA. Use reanalizar desde la evaluación.');
            }

            $interaction->update(['status' => 'queued']);
            ScoreTranscriptJob::dispatch($interaction->id)->onQueue('ai-scoring');

            return redirect()->route('transcripts.show', $interaction)
                ->with('success', 'Evaluación IA enviada a cola. El monitor la revisará antes de publicarla.');
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

        if (!Campaign::forUser($user)->whereKey($validated['campaign_id'])->exists()) {
            abort(403, 'No tiene permiso para mover esta transcripción a la campaña seleccionada.');
        }

        // Obtener supervisor de asignación activa
        $assignment = $this->visibleAssignmentForUpload($user, (int) $validated['campaign_id'], (int) $validated['agent_id']);

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
        if ($interaction->file_path && Storage::disk($this->privateDisk())->exists($interaction->file_path)) {
            Storage::disk($this->privateDisk())->delete($interaction->file_path);
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

    private function privateDisk(): string
    {
        return config('filesystems.default', 'local');
    }

    private function visibleAssignmentForUpload($user, int $campaignId, int $agentId): ?CampaignUserAssignment
    {
        $query = CampaignUserAssignment::where([
            'campaign_id' => $campaignId,
            'agent_id' => $agentId,
            'is_active' => true,
        ]);

        if ($user->hasRole('supervisor')) {
            $query->where('supervisor_id', $user->id);
        }

        if ($user->hasRole('agent')) {
            $query->where('agent_id', $user->id);
        }

        return $query->first();
    }

    private function uploadErrorMessage(UploadedFile $file): string
    {
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE => 'PHP rechazó el archivo por upload_max_filesize. Valor actual detectado: '
                . ini_get('upload_max_filesize') . '. Reinicie el servidor con: php -c php.ini artisan serve --host=127.0.0.1 --port=8000',
            UPLOAD_ERR_FORM_SIZE => 'El formulario rechazó el archivo por tamaño máximo declarado en HTML.',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente. Intente nuevamente y evite cerrar o refrescar la página durante la carga.',
            UPLOAD_ERR_NO_TMP_DIR => 'PHP no tiene carpeta temporal para recibir el archivo. Configure upload_tmp_dir o permisos de /tmp.',
            UPLOAD_ERR_CANT_WRITE => 'PHP no pudo escribir el archivo temporal. Revise permisos y espacio disponible en disco.',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP bloqueó la carga del archivo.',
            default => 'PHP no pudo recibir el archivo. Código de error: ' . $file->getError()
                . '. Límite actual: upload_max_filesize=' . ini_get('upload_max_filesize')
                . ', post_max_size=' . ini_get('post_max_size') . '.',
        };
    }
}
