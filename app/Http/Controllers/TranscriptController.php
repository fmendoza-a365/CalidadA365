<?php

namespace App\Http\Controllers;

use App\Jobs\ScoreTranscriptJob;
use App\Jobs\TranscribeAudioJob;
use App\Models\Campaign;
use App\Models\CampaignUserAssignment;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\User;
use App\Support\TranscriptAudioTimeline;
use App\Support\TranscriptConversationParser;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TranscriptController extends Controller
{
    use Concerns\StreamsAudio;
    public const CHANNEL_OPTIONS = [
        'call' => 'Llamada',
        'whatsapp' => 'WhatsApp',
        'chat' => 'Chat',
        'email' => 'Correo',
        'ticket' => 'Ticket',
        'other' => 'Otro',
    ];

    public const DIRECTION_OPTIONS = [
        'inbound' => 'Inbound',
        'outbound' => 'Outbound',
        'internal' => 'Interna',
    ];

    public const OUTCOME_OPTIONS = [
        'resolved' => 'Resuelto',
        'unresolved' => 'No resuelto',
        'escalated' => 'Escalado',
        'abandoned' => 'Abandono',
        'follow_up' => 'Seguimiento',
    ];

    public const PRIORITY_OPTIONS = [
        'normal' => 'Normal',
        'high' => 'Alta',
        'critical' => 'Crítica',
        'complaint' => 'Reclamo',
        'risk' => 'Riesgo',
    ];

    public const LANGUAGE_OPTIONS = [
        'es' => 'Español',
        'en' => 'Inglés',
        'pt' => 'Portugués',
        'other' => 'Otro',
    ];

    public const DIARIZATION_OPTIONS = [
        'auto' => 'Automática',
        'single_channel' => 'Canal único mezclado',
        'separate_channels' => 'Canales separados',
    ];

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
        $query = Interaction::with(['campaign.parent', 'agent', 'supervisor', 'uploadedBy'])
            ->forUser($user);

        if ($request->campaign_id) {
            $query->whereIn('campaign_id', Campaign::idsForFilter($request->campaign_id));
        } elseif ($request->parent_campaign_id) {
            $query->whereIn('campaign_id', Campaign::idsForFilter($request->parent_campaign_id));
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->channel) {
            $query->where('channel', $request->channel);
        }

        if ($request->priority) {
            $query->where('priority', $request->priority);
        }

        if ($request->uploaded_by) {
            $query->where('uploaded_by', $request->uploaded_by);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('call_sn', 'like', "%{$search}%")
                    ->orWhere('external_id', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%")
                    ->orWhere('contact_reason', 'like', "%{$search}%")
                    ->orWhere('customer_reference', 'like', "%{$search}%");

                if ($this->isIntegerKeySearch($search)) {
                    $query->orWhere($query->getModel()->getQualifiedKeyName(), (int) $search);
                }
            });
        }

        $interactions = $query->latest('occurred_at')->paginate(20);
        $campaigns = Campaign::active()->forUser($user)->orderedForSelect()->get();
        $formOptions = $this->formOptions();
        $uploaders = User::whereIn('id', Interaction::forUser($user)->select('uploaded_by')->distinct())
            ->orderBy('name')
            ->get(['id', 'name', 'paternal_surname', 'maternal_surname']);

        return view('transcripts.index', compact('interactions', 'campaigns', 'formOptions', 'uploaders'));
    }

    private function isIntegerKeySearch(string $search): bool
    {
        if (! ctype_digit($search)) {
            return false;
        }

        $normalized = ltrim($search, '0');
        if ($normalized === '') {
            return true;
        }

        $maxInteger = (string) PHP_INT_MAX;

        return strlen($normalized) < strlen($maxInteger)
            || (strlen($normalized) === strlen($maxInteger) && strcmp($normalized, $maxInteger) <= 0);
    }

    public function create()
    {
        $user = auth()->user();
        $campaigns = Campaign::active()
            ->forUser($user)
            ->operational()
            ->with('parent')
            ->orderedForSelect()
            ->get();
        $campaignIds = $campaigns->pluck('id');
        $operationalById = $campaigns->keyBy('id');
        $parentIds = $campaigns
            ->map(fn (Campaign $campaign) => $campaign->parent_id ?: $campaign->id)
            ->unique()
            ->values();

        $parentCampaigns = Campaign::active()
            ->forUser($user)
            ->parents()
            ->whereIn('id', $parentIds)
            ->with(['children' => function ($query) use ($user, $campaignIds) {
                $query->active()
                    ->forUser($user)
                    ->whereIn('id', $campaignIds)
                    ->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

        $subcampaignsByParent = $parentCampaigns->mapWithKeys(function (Campaign $parent) use ($operationalById) {
            $children = $parent->children
                ->filter(fn (Campaign $child) => $operationalById->has($child->id))
                ->values();

            $options = $children->isNotEmpty()
                ? $children
                : ($operationalById->has($parent->id) ? collect([$operationalById->get($parent->id)]) : collect());

            return [
                (string) $parent->id => $options->map(function (Campaign $campaign) use ($parent) {
                    return [
                        'id' => $campaign->id,
                        'name' => $campaign->isSubcampaign() ? $campaign->name : 'General',
                        'parent_id' => $parent->id,
                        'is_legacy_parent' => ! $campaign->isSubcampaign(),
                    ];
                })->values(),
            ];
        });

        $selectedCampaign = old('campaign_id') ? $operationalById->get((int) old('campaign_id')) : null;
        $selectedParentId = $selectedCampaign ? (string) ($selectedCampaign->parent_id ?: $selectedCampaign->id) : '';

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
        $assignmentsQuery = \App\Models\CampaignUserAssignment::with(['agent', 'supervisor'])
            ->whereIn('campaign_id', $campaignIds)
            ->where('is_active', true);

        if ($user->hasRole('supervisor')) {
            $assignmentsQuery->where('supervisor_id', $user->id);
            $agentIds = $assignmentsQuery->pluck('agent_id');
            $agents = User::whereIn('id', $agentIds)->orderBy('name')->get();
        } elseif ($user->hasRole('agent')) {
            $agents = collect([$user]);
            $assignmentsQuery->where('agent_id', $user->id);
        } else {
            $agents = User::role('agent')->orderBy('name')->limit(500)->get();
        }

        $assignments = $assignmentsQuery->get();
        $agentsByCampaign = $assignments->groupBy('campaign_id')->map(function ($assignedAgents) {
            return $assignedAgents->map(function ($assignment) {
                return [
                    'id' => $assignment->agent->id,
                    'name' => $assignment->agent->full_name,
                    'supervisor_id' => $assignment->supervisor_id,
                ];
            })->unique('id')->values();
        });

        $supervisorsByCampaign = $assignments->groupBy('campaign_id')->map(function ($assignedAgents) {
            return $assignedAgents->filter(fn($a) => $a->supervisor)->map(function ($assignment) {
                return [
                    'id' => $assignment->supervisor->id,
                    'name' => $assignment->supervisor->full_name,
                ];
            })->unique('id')->values();
        });

        $formOptions = $this->formOptions();

        return view('transcripts.create', compact(
            'campaigns',
            'parentCampaigns',
            'subcampaignsByParent',
            'selectedParentId',
            'agents',
            'qualityForms',
            'agentsByCampaign',
            'supervisorsByCampaign',
            'formOptions'
        ));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $lock = \Illuminate\Support\Facades\Cache::lock('upload_transcript_'.auth()->id(), 30);

        if (! $lock->get()) {
            return back()->with('info', 'Ya hay una carga en proceso. Por favor espere.');
        }

        try {
            foreach (Arr::wrap($request->file('transcript_files', [])) as $index => $file) {
                if ($file instanceof UploadedFile && ! $file->isValid()) {
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
                'call_sn' => 'nullable|string|max:100',
                'external_id' => 'nullable|string|max:120',
                'channel' => 'nullable|in:'.implode(',', array_keys(self::CHANNEL_OPTIONS)),
                'direction' => 'nullable|in:'.implode(',', array_keys(self::DIRECTION_OPTIONS)),
                'language' => 'nullable|in:'.implode(',', array_keys(self::LANGUAGE_OPTIONS)),
                'contact_reason' => 'nullable|string|max:160',
                'outcome' => 'nullable|in:'.implode(',', array_keys(self::OUTCOME_OPTIONS)),
                'customer_reference' => 'nullable|string|max:120',
                'queue_name' => 'nullable|string|max:120',
                'product_name' => 'nullable|string|max:120',
                'priority' => 'nullable|in:'.implode(',', array_keys(self::PRIORITY_OPTIONS)),
                'tags' => 'nullable|string|max:500',
                'diarization_mode' => 'nullable|in:'.implode(',', array_keys(self::DIARIZATION_OPTIONS)),
                'analyze_emotion' => 'nullable|boolean',
                'detect_critical_compliance' => 'nullable|boolean',
                'ai_context' => 'nullable|string|max:1000',
                'transcript_files' => 'required|array|min:1|max:50',
                'transcript_files.*' => "required|file|extensions:{$allExtensions}|mimetypes:{$allowedMimeTypes}|max:".self::UPLOAD_MAX_KB,
            ], [
                'transcript_files.*.uploaded' => 'El archivo no pudo subirse. Verifique que pese menos de 100 MB y que PHP permita cargas de al menos 100 MB.',
                'transcript_files.*.extensions' => 'Formato no permitido. Use TXT o audio: '.implode(', ', self::AUDIO_EXTENSIONS).'.',
                'transcript_files.*.mimetypes' => 'El archivo no parece ser un TXT o audio válido. Si el audio viene de WhatsApp, súbalo como .opus, .ogg o .m4a.',
                'transcript_files.*.max' => 'Cada archivo puede pesar hasta 100 MB.',
            ]);

            $callSn = $this->normalizeCallSn($validated['call_sn'] ?? null);
            $uploadMetadata = $this->uploadMetadata($validated, $request);

            if (! Campaign::forUser($user)->active()->operational()->whereKey($validated['campaign_id'])->exists()) {
                abort(403, 'No tiene permiso para cargar audios en esta subcampaña.');
            }

            if (! empty($validated['quality_form_id'])) {
                $formIsValid = \App\Models\QualityForm::whereKey($validated['quality_form_id'])
                    ->where('campaign_id', $validated['campaign_id'])
                    ->whereHas('versions', function ($q) {
                        $q->where('status', 'published');
                    })
                    ->exists();

                if (! $formIsValid) {
                    return back()->withErrors([
                        'quality_form_id' => 'La ficha seleccionada no pertenece a esta subcampaña o no está publicada.',
                    ])->withInput();
                }
            }

            // Obtener supervisor de asignación activa
            $assignment = $this->visibleAssignmentForUpload($user, (int) $validated['campaign_id'], (int) $validated['agent_id']);

            if (! $assignment) {
                return back()->withErrors(['agent_id' => 'El asesor no está asignado a esta subcampaña.']);
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
                    'call_sn' => $callSn,
                    'external_id' => $this->normalizeNullableString($validated['external_id'] ?? null),
                    'source_type' => $isAudio ? 'audio' : 'text',
                    'channel' => $validated['channel'] ?? 'call',
                    'direction' => $validated['direction'] ?? null,
                    'contact_reason' => $this->normalizeNullableString($validated['contact_reason'] ?? null),
                    'outcome' => $validated['outcome'] ?? null,
                    'customer_reference' => $this->normalizeNullableString($validated['customer_reference'] ?? null),
                    'queue_name' => $this->normalizeNullableString($validated['queue_name'] ?? null),
                    'product_name' => $this->normalizeNullableString($validated['product_name'] ?? null),
                    'priority' => $validated['priority'] ?? 'normal',
                    'batch_id' => $batchId,
                    'metadata' => $uploadMetadata,
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

    public function show(
        Interaction $interaction,
        TranscriptConversationParser $conversationParser,
        TranscriptAudioTimeline $audioTimelineBuilder
    ) {
        $user = auth()->user();
        if (! Interaction::forUser($user)->where('id', $interaction->id)->exists()) {
            abort(403, 'No tiene permiso para ver esta transcripción.');
        }

        $interaction->load(['campaign.parent', 'agent', 'supervisor', 'uploadedBy', 'evaluation.items.subAttribute']);
        $conversationTurns = $conversationParser->parse($interaction->transcript_text);
        $audioTimeline = null;

        if ($interaction->isAudio()) {
            $audioTimeline = $audioTimelineBuilder->build(
                $conversationTurns,
                $interaction->audio_duration,
                $this->audioMetadata($interaction, $conversationParser)
            );
            $conversationTurns = $audioTimeline['turns'];
        }

        $formOptions = $this->formOptions();

        return view('transcripts.show', compact('interaction', 'conversationTurns', 'audioTimeline', 'formOptions'));
    }

    public function evaluate(Interaction $interaction)
    {
        $user = auth()->user();
        if (! Interaction::forUser($user)->where('id', $interaction->id)->exists()) {
            abort(403, 'No tiene permiso para evaluar esta transcripción.');
        }

        if (! $user->can('create_evaluations') && ! $user->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator'])) {
            abort(403, 'No tiene permiso para iniciar evaluaciones IA.');
        }

        if ($interaction->isTranscribing()) {
            return back()->with('info', 'La transcripción del audio aún está en proceso.');
        }

        $lock = \Illuminate\Support\Facades\Cache::lock('evaluate_interaction_'.$interaction->id, 60);

        if (! $lock->get()) {
            return back()->with('info', 'La evaluación ya está en proceso.');
        }

        try {
            if ($interaction->manualEvaluation()->exists()) {
                return back()->with('error', 'Esta interacción ya tiene una evaluación final manual.');
            }

            if ($interaction->aiEvaluation()->exists()) {
                return back()->with('error', 'Esta interacción ya tiene una evaluación IA. Use reanalizar desde la evaluación.');
            }

            $formVersion = $interaction->scorableFormVersion();
            if (! $formVersion) {
                return back()->with('error', 'Esta interacción no tiene una ficha de calidad publicada para evaluar.');
            }

            Evaluation::createPendingAiForInteraction($interaction, $formVersion, $user, [
                'source' => 'manual_request',
            ]);

            $interaction->update(['status' => 'queued']);
            ScoreTranscriptJob::dispatch($interaction->id)->onQueue('ai-scoring');

            return redirect()->route('transcripts.show', $interaction)
                ->with('success', 'Evaluación IA creada y enviada a cola. Puedes verla en Evaluaciones como Pendiente IA mientras se procesa.');
        } finally {
            $lock->release();
        }
    }

    public function edit(Interaction $interaction)
    {
        $user = auth()->user();
        if (! Interaction::forUser($user)->where('id', $interaction->id)->exists()) {
            abort(403, 'No tiene permiso para editar esta transcripción.');
        }

        $interaction->loadMissing('campaign.parent');
        $campaigns = Campaign::active()
            ->forUser($user)
            ->operational()
            ->orderedForSelect()
            ->get();
        // Similarly filter agents
        if ($user->hasRole('supervisor')) {
            $agentIds = \App\Models\CampaignUserAssignment::where('supervisor_id', $user->id)
                ->where('is_active', true)
                ->pluck('agent_id');
            $agents = User::whereIn('id', $agentIds)->orderBy('name')->get();
        } elseif ($user->hasRole('agent')) {
            $agents = collect([$user]);
        } else {
            $agents = User::role('agent')->orderBy('name')->limit(500)->get();
        }

        $formOptions = $this->formOptions();

        return view('transcripts.edit', compact('interaction', 'campaigns', 'agents', 'formOptions'));
    }

    public function update(\App\Http\Requests\UpdateTranscriptRequest $request, Interaction $interaction)
    {
        $user = auth()->user();
        $validated = $request->validated();

        $callSn = $this->normalizeCallSn($validated['call_sn'] ?? null);
        $metadata = $this->replaceUploadMetadata($interaction->metadata ?? [], $this->uploadMetadata($validated, $request));

        if (! Campaign::forUser($user)->active()->operational()->whereKey($validated['campaign_id'])->exists()) {
            abort(403, 'No tiene permiso para mover esta transcripción a la subcampaña seleccionada.');
        }

        // Obtener supervisor de asignación activa
        $assignment = $this->visibleAssignmentForUpload($user, (int) $validated['campaign_id'], (int) $validated['agent_id']);

        if (! $assignment) {
            return back()->withErrors(['agent_id' => 'El asesor no está asignado a esta subcampaña.']);
        }

        $interaction->update([
            'campaign_id' => $validated['campaign_id'],
            'agent_id' => $validated['agent_id'],
            'supervisor_id' => $assignment->supervisor_id,
            'occurred_at' => $validated['occurred_at'],
            'call_sn' => $callSn,
            'external_id' => $this->normalizeNullableString($validated['external_id'] ?? null),
            'channel' => $validated['channel'] ?? 'call',
            'direction' => $validated['direction'] ?? null,
            'contact_reason' => $this->normalizeNullableString($validated['contact_reason'] ?? null),
            'outcome' => $validated['outcome'] ?? null,
            'customer_reference' => $this->normalizeNullableString($validated['customer_reference'] ?? null),
            'queue_name' => $this->normalizeNullableString($validated['queue_name'] ?? null),
            'product_name' => $this->normalizeNullableString($validated['product_name'] ?? null),
            'priority' => $validated['priority'] ?? 'normal',
            'metadata' => $metadata,
            'transcript_text' => $validated['transcript_text'] ?? $interaction->transcript_text,
        ]);

        return redirect()->route('transcripts.show', $interaction)
            ->with('success', 'Transcripción actualizada exitosamente.');
    }

    public function destroy(Interaction $interaction)
    {
        $user = auth()->user();
        // Typically only admin/managers can delete, but let's at least enforce they can see it
        if (! Interaction::forUser($user)->where('id', $interaction->id)->exists()) {
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

    private function formOptions(): array
    {
        return [
            'channels' => self::CHANNEL_OPTIONS,
            'directions' => self::DIRECTION_OPTIONS,
            'outcomes' => self::OUTCOME_OPTIONS,
            'priorities' => self::PRIORITY_OPTIONS,
            'languages' => self::LANGUAGE_OPTIONS,
            'diarizationModes' => self::DIARIZATION_OPTIONS,
        ];
    }

    private function uploadMetadata(array $validated, Request $request): array
    {
        return [
            'upload' => [
                'origin' => 'manual_upload',
                'language' => $validated['language'] ?? 'es',
                'tags' => $this->tagList($validated['tags'] ?? null),
                'diarization_mode' => $validated['diarization_mode'] ?? 'auto',
                'analysis_options' => [
                    'emotion' => $request->boolean('analyze_emotion'),
                    'critical_compliance' => $request->boolean('detect_critical_compliance'),
                ],
                'ai_context' => $this->normalizeNullableString($validated['ai_context'] ?? null),
            ],
        ];
    }

    private function replaceUploadMetadata(array $currentMetadata, array $uploadMetadata): array
    {
        $currentMetadata['upload'] = $uploadMetadata['upload'] ?? [];

        return $currentMetadata;
    }

    private function normalizeCallSn(?string $callSn): ?string
    {
        $callSn = trim((string) $callSn);

        return $callSn !== '' ? $callSn : null;
    }

    private function normalizeNullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function tagList(?string $tags): array
    {
        return collect(explode(',', (string) $tags))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }

    private function visibleAssignmentForUpload($user, int $campaignId, int $agentId): ?CampaignUserAssignment
    {
        $query = CampaignUserAssignment::where('campaign_id', $campaignId)
            ->where([
                'agent_id' => $agentId,
                'is_active' => true,
            ])
            ->latest('id');

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
                .ini_get('upload_max_filesize').'. Reinicie el servidor local con: php -c php.ini -S 127.0.0.1:8000 -t public local-server.php',
            UPLOAD_ERR_FORM_SIZE => 'El formulario rechazó el archivo por tamaño máximo declarado en HTML.',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente. Intente nuevamente y evite cerrar o refrescar la página durante la carga.',
            UPLOAD_ERR_NO_TMP_DIR => 'PHP no tiene carpeta temporal para recibir el archivo. Configure upload_tmp_dir o permisos de /tmp.',
            UPLOAD_ERR_CANT_WRITE => 'PHP no pudo escribir el archivo temporal. Revise permisos y espacio disponible en disco.',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP bloqueó la carga del archivo.',
            default => 'PHP no pudo recibir el archivo. Código de error: '.$file->getError()
                .'. Límite actual: upload_max_filesize='.ini_get('upload_max_filesize')
                .', post_max_size='.ini_get('post_max_size').'.',
        };
    }
}
