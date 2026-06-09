<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TranscriptController;
use App\Models\AgentResponse;
use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\InsightReport;
use App\Models\QualityForm;
use App\Models\User;
use App\Services\AgentFeedbackResponseService;
use App\Services\QualityAnalyticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;

class MobileDashboardController extends Controller
{
    public function __construct(private readonly QualityAnalyticsService $analytics)
    {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $this->dashboardFilters($request);
        $summary = $this->summaryPayload($user);
        $overview = $this->analytics->getOverviewStats($filters);
        $feedback = $this->analytics->getFeedbackStats($filters);
        $qualityTrendSeries = $this->analytics->getQualityTrendSeries($filters);
        $mpTrendSeries = $this->analytics->getMpTrendSeries($filters);
        $feedbackTrendSeries = $this->analytics->getFeedbackTrendSeries($filters);
        $audioPerformance = $this->analytics->getAudioUploadPerformance($filters, 6);
        $ranking = collect($this->analytics->getAgentRanking($filters, 8))
            ->map(fn (array $agent, int $index) => [
                ...$agent,
                'position' => $index + 1,
                'score_label' => $this->formatPercent($agent['avg_score'] ?? 0),
                'level' => $this->levelName((float) ($agent['avg_score'] ?? 0)),
            ])
            ->values();

        $recentEvaluations = $this->visibleEvaluations($user)
            ->with(['agentResponse', 'agent', 'campaign.parent', 'interaction', 'evaluator', 'items.subAttribute.attribute'])
            ->latest('evaluations.created_at')
            ->limit(8)
            ->get()
            ->map(fn (Evaluation $evaluation) => $this->evaluationPayload($evaluation))
            ->values();

        $alerts = collect();
        $this->alertsCollection($user, 8)->each(fn (array $alert) => $alerts->push($alert));

        $payload = [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'paternal_surname' => $user->paternal_surname,
                'maternal_surname' => $user->maternal_surname,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'email' => $user->email,
                'personal_email' => $user->personal_email,
                'personal_phone' => $user->personal_phone,
                'company_phone' => $user->company_phone,
                'birthdate' => $user->birthdate?->format('Y-m-d'),
                'gender' => $user->gender,
                'address' => $user->address,
                'department' => $user->department,
                'province' => $user->province,
                'district' => $user->district,
                'roles' => $user->roles->pluck('name')->values(),
                'primary_view' => $user->hasRole('agent') ? 'agent' : 'executive',
                'avatar_url' => $user->avatar_url,
            ],
            'filter_options' => $this->filterOptions($user),
            'filters' => $filters,
            'summary' => $summary,
            'overview' => $overview,
            'feedback' => $feedback,
            'quality_trend' => collect($qualityTrendSeries['day'])->take(-7)->values(),
            'campaigns' => collect($this->analytics->getQualityGrouped('campaign', $filters))->take(6)->values(),
            'top_defects' => collect($this->analytics->getTopDefects($filters, 6))->values(),
            'ranking' => $ranking,
            'alerts' => $alerts,
            'evaluations' => $recentEvaluations,
            'modules' => $this->modulesPayload($user, $filters),
            'charts' => [
                'quality' => $qualityTrendSeries,
                'malas_practicas' => $mpTrendSeries,
                'feedback' => $feedbackTrendSeries,
                'evals_by_campaign' => $this->analytics->getEvalsByCampaign($filters),
            ],
            'audio_productivity' => $audioPerformance,
            'generated_at' => now()->toIso8601String(),
        ];

        if ($user->hasRole('agent')) {
            $league = $this->analytics->getAgentLeague((float) ($overview['average_score'] ?? 0));
            $payload['agent'] = [
                'league' => [
                    'name' => $league['name'],
                    'color' => $league['color'],
                    'score' => (float) ($overview['average_score'] ?? 0),
                    'score_label' => $this->formatPercent($overview['average_score'] ?? 0),
                ],
                'match_history' => $this->analytics->getAgentMatchHistory($filters, 8)
                    ->map(fn (Evaluation $evaluation) => $this->evaluationPayload($evaluation))
                    ->values(),
            ];
        }

        return response()->json($payload);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'summary' => $this->summaryPayload($user),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min(max((int) $request->query('limit', 20), 1), 50);

        $payload = $this->alertsCollection($user, $limit);

        return response()->json([
            'alerts' => $payload,
            'count' => $payload->count(),
        ]);
    }

    public function evaluations(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);

        $evaluations = $this->visibleEvaluations($user)
            ->with(['agentResponse', 'agent', 'campaign.parent', 'interaction', 'evaluator', 'items.subAttribute.attribute'])
            ->latest('evaluations.created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $evaluations->getCollection()
                ->map(fn (Evaluation $evaluation) => $this->evaluationPayload($evaluation))
                ->values(),
            'meta' => [
                'current_page' => $evaluations->currentPage(),
                'per_page' => $evaluations->perPage(),
                'total' => $evaluations->total(),
                'last_page' => $evaluations->lastPage(),
            ],
        ]);
    }

    public function showEvaluation(Request $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorize('view', $evaluation);

        return response()->json([
            'evaluation' => $this->evaluationPayload($evaluation->loadMissing(['agentResponse', 'agent', 'campaign.parent', 'interaction', 'evaluator', 'items.subAttribute.attribute'])),
        ]);
    }

    public function markEvaluationViewed(Request $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorize('view', $evaluation);

        if ($request->user()->hasRole('agent') && $evaluation->isVisibleToAgent() && ! $evaluation->agent_viewed_at) {
            $evaluation->forceFill(['agent_viewed_at' => now()])->save();
        }

        return response()->json([
            'evaluation' => $this->evaluationPayload($evaluation->fresh(['agentResponse', 'agent', 'campaign.parent', 'interaction', 'evaluator', 'items.subAttribute.attribute'])),
        ]);
    }

    public function respondEvaluation(Request $request, Evaluation $evaluation, AgentFeedbackResponseService $responses): JsonResponse
    {
        $this->authorize('respond', $evaluation);

        if ($evaluation->agentResponse()->exists()) {
            throw ValidationException::withMessages([
                'response_type' => ['Ya registraste una respuesta para esta evaluacion.'],
            ]);
        }

        $validated = $request->validate([
            'response_type' => ['required', 'in:accept,dispute,reviewed,commitment'],
            'commitment_comment' => ['required_if:response_type,accept,reviewed,commitment', 'nullable', 'string', 'max:5000'],
            'dispute_reason' => ['required_if:response_type,dispute', 'nullable', 'string', 'max:5000'],
            'disputed_items' => ['nullable', 'array'],
        ]);

        $responses->store($evaluation, $request->user(), $validated);

        return response()->json([
            'message' => 'Respuesta registrada.',
            'evaluation' => $this->evaluationPayload($evaluation->fresh(['agentResponse', 'agent', 'campaign.parent', 'interaction', 'evaluator', 'items.subAttribute.attribute'])),
        ]);
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string'],
            'device_id' => ['nullable', 'string'],
            'platform' => ['nullable', 'string', 'in:android,ios'],
        ]);

        $user = $request->user();

        \App\Models\MobileDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $validated['device_id'] ?? $validated['fcm_token'],
            ],
            [
                'fcm_token' => $validated['fcm_token'],
                'platform' => $validated['platform'] ?? 'android',
            ]
        );

        return response()->json(['message' => 'Dispositivo registrado.']);
    }

    public function unregisterDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string'],
        ]);

        \App\Models\MobileDevice::where('fcm_token', $validated['fcm_token'])->delete();

        return response()->json(['message' => 'Dispositivo desregistrado.']);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);

        $notifications = $user->notifications()->paginate($perPage);

        return response()->json([
            'data' => $notifications->getCollection()->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markNotificationRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'message' => 'Notificacion marcada como leida.',
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'Todas las notificaciones marcadas como leidas.',
            'unread_count' => 0,
        ]);
    }

    public function transcriptAudio(Request $request, Interaction $interaction, \App\Http\Controllers\TranscriptAudioController $audioController): Response
    {
        if (! Interaction::query()->forUser($request->user())->whereKey($interaction->id)->exists()) {
            abort(403, 'No tiene permiso para escuchar esta transcripcion.');
        }

        return $audioController->audio($request, $interaction);
    }

    public function feedbackAudio(Request $request, Evaluation $evaluation): Response
    {
        abort_unless($request->user()->can('view', $evaluation), 403);

        if ($evaluation->feedback_audio_status !== 'ready' || ! $evaluation->feedback_audio_path) {
            abort(404);
        }

        $disk = $evaluation->feedback_audio_disk ?: config('ai.feedback_tts.audio_disk', config('filesystems.default'));
        $storage = Storage::disk($disk);

        if (! $storage->exists($evaluation->feedback_audio_path)) {
            abort(404);
        }

        return response($storage->get($evaluation->feedback_audio_path), 200, [
            'Content-Type' => 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="feedback-evaluacion-'.$evaluation->id.'.mp3"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function visibleEvaluations(User $user): Builder
    {
        return Evaluation::query()
            ->forUser($user)
            ->where(function (Builder $query) {
                $query
                    ->where('type', 'manual')
                    ->orWhereDoesntHave('interaction.manualEvaluation');
            });
    }

    private function visibleInteractions(User $user): Builder
    {
        return Interaction::query()->forUser($user);
    }

    private function dashboardFilters(Request $request): array
    {
        return [
            'start_date' => $request->input('start_date', now()->startOfMonth()->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
            'campaign_id' => $request->input('campaign_id'),
            'parent_campaign_id' => $request->input('parent_campaign_id'),
            'supervisor_id' => $request->input('supervisor_id'),
            'agent_id' => $request->input('agent_id'),
        ];
    }

    private function filterOptions(User $user): array
    {
        // Agents only see their own data — no filter options needed
        if ($user->hasRole('agent')) {
            return [
                'parent_campaigns' => [],
                'subcampaigns' => [],
                'supervisors' => [],
                'agents' => [],
            ];
        }

        // Cache filter options per user for 5 minutes
        $cacheKey = 'mobile_filters_user_'.$user->id;

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($user) {
            $campaigns = Campaign::forUser($user)->active()->orderedForSelect()->get(['id', 'name', 'parent_id']);
            $parentCampaigns = $campaigns->whereNull('parent_id')->values();
            $subcampaigns = $campaigns->whereNotNull('parent_id')->values();

            $supervisors = User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'supervisor'))
                ->orderBy('name')
                ->get(['id', 'name', 'paternal_surname'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->full_name])
                ->values();

            // Supervisors only see their team agents
            if ($user->hasRole('supervisor')) {
                $agents = User::query()
                    ->whereHas('agentAssignments', fn ($q) => $q->where('supervisor_id', $user->id)->where('is_active', true))
                    ->orderBy('name')
                    ->get(['id', 'name', 'paternal_surname'])
                    ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->full_name])
                    ->values();

                return [
                    'parent_campaigns' => $parentCampaigns->map(fn (Campaign $c) => ['id' => $c->id, 'name' => $c->name])->values(),
                    'subcampaigns' => $subcampaigns->map(fn (Campaign $c) => ['id' => $c->id, 'name' => $c->name, 'parent_id' => $c->parent_id])->values(),
                    'supervisors' => [],
                    'agents' => $agents,
                ];
            }

            $agents = User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'agent'))
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name', 'paternal_surname'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->full_name])
                ->values();

            return [
                'parent_campaigns' => $parentCampaigns->map(fn (Campaign $c) => ['id' => $c->id, 'name' => $c->name])->values(),
                'subcampaigns' => $subcampaigns->map(fn (Campaign $c) => ['id' => $c->id, 'name' => $c->name, 'parent_id' => $c->parent_id])->values(),
                'supervisors' => $supervisors,
                'agents' => $agents,
            ];
        });
    }

    private function summaryPayload(User $user): array
    {
        $baseQuery = $this->visibleEvaluations($user);
        $scoredQuery = (clone $baseQuery)->whereNotNull('percentage_score');

        $summary = [
            'total_evaluations' => (clone $baseQuery)->count(),
            'average_score' => round((float) ($scoredQuery->avg('percentage_score') ?? 0), 1),
            'pending_review' => (clone $baseQuery)->whereIn('status', [
                Evaluation::STATUS_PENDING_MONITOR_REVIEW,
                Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
            ])->count(),
            'critical_scores' => (clone $baseQuery)->where('percentage_score', '<', 70)->whereNotNull('percentage_score')->count(),
            'disputed' => (clone $baseQuery)->where('status', Evaluation::STATUS_AGENT_DISPUTED)->count(),
            'ai_failed' => (clone $baseQuery)->where('status', Evaluation::STATUS_AI_FAILED)->count(),
            'closed' => (clone $baseQuery)->where('status', Evaluation::STATUS_CLOSED)->count(),
            'processing_audio' => Interaction::query()
                ->forUser($user)
                ->whereIn('transcription_status', ['pending', 'processing'])
                ->count(),
            'unread_notifications' => $user->unreadNotifications()->count(),
            'latest_evaluation_at' => optional((clone $baseQuery)->latest('evaluations.created_at')->first()?->created_at)->toIso8601String(),
        ];

        $summary['open_alerts'] = $summary['critical_scores']
            + $summary['disputed']
            + $summary['ai_failed']
            + $summary['unread_notifications'];

        return $summary;
    }

    private function alertsCollection(User $user, int $limit): Collection
    {
        $alerts = collect();

        $user->unreadNotifications()
            ->latest()
            ->limit(10)
            ->get()
            ->each(function ($notification) use ($alerts) {
                $data = is_array($notification->data) ? $notification->data : [];
                $alerts->push([
                    'id' => 'notification-'.$notification->id,
                    'type' => 'notification',
                    'severity' => 'info',
                    'title' => $data['title'] ?? 'Notificacion pendiente',
                    'description' => $data['message'] ?? $data['body'] ?? 'Tienes una notificacion sin leer.',
                    'created_at' => $notification->created_at?->toIso8601String(),
                    'action_url' => url('/notifications'),
                ]);
            });

        $criticalCandidates = $this->visibleEvaluations($user)
            ->with(['agent', 'campaign.parent', 'interaction'])
            ->where(function (Builder $query) {
                $query
                    ->where('percentage_score', '<', 70)
                    ->orWhereIn('status', [
                        Evaluation::STATUS_AI_FAILED,
                        Evaluation::STATUS_AGENT_DISPUTED,
                        Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
                    ]);
            })
            ->latest('evaluations.created_at')
            ->limit(40)
            ->get();

        $recentCandidates = $this->visibleEvaluations($user)
            ->with(['agent', 'campaign.parent', 'interaction'])
            ->latest('evaluations.created_at')
            ->limit(40)
            ->get();

        $criticalCandidates
            ->merge($recentCandidates)
            ->unique('id')
            ->flatMap(fn (Evaluation $evaluation) => $this->alertsForEvaluation($evaluation))
            ->each(fn (array $alert) => $alerts->push($alert));

        return $alerts
            ->sortByDesc(fn (array $alert) => $alert['created_at'] ?? '')
            ->values()
            ->take($limit)
            ->values();
    }

    private function modulesPayload(User $user, array $filters): array
    {
        $visibleEvaluations = $this->visibleEvaluations($user);
        $visibleInteractions = $this->visibleInteractions($user);
        $publishedEvaluations = (clone $visibleEvaluations)->whereNotNull('visible_to_agent_at');
        $feedbackResponded = (clone $publishedEvaluations)->whereHas('agentResponse')->count();
        $feedbackPending = (clone $publishedEvaluations)->whereDoesntHave('agentResponse')->count();

        return [
            'dashboard' => [
                'title' => 'Dashboard',
                'description' => 'Avance de calidad, alertas y feedback.',
                'count' => (clone $visibleEvaluations)->count(),
                'url' => url('/dashboard'),
            ],
            'transcripts' => [
                'summary' => [
                    'total' => (clone $visibleInteractions)->count(),
                    'audio' => (clone $visibleInteractions)->where('source_type', 'audio')->count(),
                    'processing' => (clone $visibleInteractions)->whereIn('transcription_status', ['pending', 'processing'])->count(),
                    'failed' => (clone $visibleInteractions)->where('transcription_status', 'failed')->count(),
                ],
                'items' => $this->transcriptsPayload($user),
                'url' => url('/transcripts'),
            ],
            'evaluations' => [
                'summary' => [
                    'total' => (clone $visibleEvaluations)->count(),
                    'pending_monitor' => (clone $visibleEvaluations)->whereIn('status', [
                        Evaluation::STATUS_PENDING_MONITOR_REVIEW,
                        Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
                    ])->count(),
                    'published' => (clone $visibleEvaluations)->whereNotNull('visible_to_agent_at')->count(),
                    'critical' => (clone $visibleEvaluations)->whereNotNull('percentage_score')->where('percentage_score', '<', 70)->count(),
                ],
                'items' => (clone $visibleEvaluations)
                    ->with(['agentResponse', 'agent', 'campaign.parent', 'interaction', 'evaluator', 'items.subAttribute.attribute'])
                    ->latest('evaluations.created_at')
                    ->limit(6)
                    ->get()
                    ->map(fn (Evaluation $evaluation) => $this->evaluationPayload($evaluation))
                    ->values(),
                'url' => url('/evaluations'),
            ],
            'campaigns' => [
                'summary' => [
                    'total' => Campaign::forUser($user)->count(),
                    'active' => Campaign::forUser($user)->active()->count(),
                ],
                'items' => $this->campaignsPayload($user, $filters),
                'url' => url('/campaigns'),
            ],
            'quality_forms' => [
                'summary' => [
                    'total' => QualityForm::forUser($user)->count(),
                    'with_context' => QualityForm::forUser($user)
                        ->where(function (Builder $query) {
                            $query
                                ->whereNotNull('operational_context_markdown')
                                ->orWhereNotNull('context_file_text');
                        })
                        ->count(),
                ],
                'items' => $this->qualityFormsPayload($user),
                'url' => url('/quality-forms'),
            ],
            'insights' => [
                'summary' => [
                    'total' => InsightReport::whereHas('campaign', fn (Builder $query) => $query->forUser($user))->count(),
                    'last_30_days' => InsightReport::whereHas('campaign', fn (Builder $query) => $query->forUser($user))
                        ->where('created_at', '>=', now()->subDays(30))
                        ->count(),
                ],
                'items' => $this->insightsPayload($user),
                'url' => url('/insights'),
            ],
            'feedback' => [
                'summary' => [
                    'published' => (clone $publishedEvaluations)->count(),
                    'viewed' => (clone $publishedEvaluations)->whereNotNull('agent_viewed_at')->count(),
                    'responded' => $feedbackResponded,
                    'pending_response' => $feedbackPending,
                    'accepted' => AgentResponse::whereHas('evaluation', fn (Builder $query) => $query->forUser($user))
                        ->where('response_type', 'accept')
                        ->count(),
                    'disputed' => AgentResponse::whereHas('evaluation', fn (Builder $query) => $query->forUser($user))
                        ->where('response_type', 'dispute')
                        ->count(),
                ],
            ],
        ];
    }

    private function transcriptsPayload(User $user): Collection
    {
        $conversationParser = new \App\Support\TranscriptConversationParser();
        return $this->visibleInteractions($user)
            ->with(['agent', 'campaign.parent', 'supervisor', 'evaluation'])
            ->latest('occurred_at')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (Interaction $interaction) => [
                'id' => $interaction->id,
                'campaign' => $interaction->campaign?->displayName(),
                'campaign_id' => $interaction->campaign_id,
                'campaign_parent' => $interaction->campaign?->parent?->name,
                'subcampaign' => $interaction->campaign?->parent ? $interaction->campaign->name : null,
                'agent' => $interaction->agent?->name,
                'supervisor' => $interaction->supervisor?->name,
                'source_type' => $interaction->source_type,
                'file_name' => $interaction->file_name,
                'status' => $interaction->status,
                'transcription_status' => $interaction->transcription_status,
                'duration_seconds' => $interaction->audio_duration,
                'duration_label' => $this->formatDuration((float) ($interaction->audio_duration ?? 0)),
                'occurred_at' => $interaction->occurred_at?->toIso8601String(),
                'transcript_text' => (string) $interaction->transcript_text,
                'transcript_excerpt' => str((string) $interaction->transcript_text)->squish()->limit(180)->toString(),
                'conversation_turns' => $conversationParser->parse($interaction->transcript_text),
                'audio_url' => $interaction->isAudio() ? url('/api/mobile/transcripts/'.$interaction->id.'/audio') : null,
                'score' => $interaction->evaluation?->percentage_score !== null ? (float) $interaction->evaluation->percentage_score : null,
                'score_label' => $interaction->evaluation?->percentage_score !== null ? $this->formatPercent($interaction->evaluation->percentage_score) : 'Sin nota',
                'url' => url('/transcripts/'.$interaction->id),
            ])
            ->values();
    }

    private function campaignsPayload(User $user, array $filters): Collection
    {
        return Campaign::forUser($user)
            ->with('parent')
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (Campaign $campaign) use ($user, $filters) {
                $campaignIds = Campaign::idsForFilter($campaign->id);
                $query = Evaluation::query()->forUser($user)->whereIn('campaign_id', $campaignIds);
                $this->applyDateFilters($query, $filters);
                $total = (clone $query)->count();
                $average = (float) ((clone $query)->avg('percentage_score') ?? 0);

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->displayName(),
                    'parent_id' => $campaign->parent_id,
                    'parent_name' => $campaign->parent?->name,
                    'subcampaign' => $campaign->parent ? $campaign->name : null,
                    'is_subcampaign' => $campaign->isSubcampaign(),
                    'active' => (bool) $campaign->is_active,
                    'target_quality' => $campaign->target_quality !== null ? (float) $campaign->target_quality : null,
                    'evaluations' => $total,
                    'average_score' => round($average, 1),
                    'score_label' => $this->formatPercent($average),
                    'interactions' => Interaction::query()->forUser($user)->whereIn('campaign_id', $campaignIds)->count(),
                    'forms' => QualityForm::query()->forUser($user)->whereIn('campaign_id', $campaignIds)->count(),
                    'url' => url('/campaigns/'.$campaign->id),
                ];
            })
            ->values();
    }

    private function qualityFormsPayload(User $user): Collection
    {
        return QualityForm::forUser($user)
            ->with(['campaign.parent', 'latestVersion'])
            ->withCount('versions')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (QualityForm $form) => [
                'id' => $form->id,
                'name' => $form->name,
                'campaign' => $form->campaign?->displayName(),
                'campaign_id' => $form->campaign_id,
                'campaign_parent' => $form->campaign?->parent?->name,
                'subcampaign' => $form->campaign?->parent ? $form->campaign->name : null,
                'versions' => (int) $form->versions_count,
                'latest_status' => $form->latestVersion?->status ?? 'Sin version',
                'has_context' => filled($form->operational_context_markdown) || filled($form->context_file_text),
                'url' => url('/quality-forms/'.$form->id),
            ])
            ->values();
    }

    private function insightsPayload(User $user): Collection
    {
        return InsightReport::whereHas('campaign', fn (Builder $query) => $query->forUser($user))
            ->with('campaign.parent')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (InsightReport $insight) => [
                'id' => $insight->id,
                'type' => $insight->type,
                'campaign' => $insight->campaign?->displayName(),
                'campaign_id' => $insight->campaign_id,
                'campaign_parent' => $insight->campaign?->parent?->name,
                'subcampaign' => $insight->campaign?->parent ? $insight->campaign->name : null,
                'date_range' => trim(($insight->date_range_start?->format('Y-m-d') ?? '').' - '.($insight->date_range_end?->format('Y-m-d') ?? ''), ' -'),
                'findings' => is_array($insight->key_findings) ? count($insight->key_findings) : 0,
                'summary' => str((string) $insight->summary_content)->stripTags()->limit(120)->toString(),
                'url' => url('/insights/'.$insight->id),
            ])
            ->values();
    }

    private function applyDateFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $query->whereBetween('evaluations.created_at', [
                \Illuminate\Support\Carbon::parse($filters['start_date'])->startOfDay(),
                \Illuminate\Support\Carbon::parse($filters['end_date'])->endOfDay(),
            ]);
        }
    }

    private function alertsForEvaluation(Evaluation $evaluation): Collection
    {
        $alerts = collect();
        $metadata = $evaluation->interaction?->metadata ?? [];
        $qualitySignals = is_array($metadata['quality_signals'] ?? null) ? $metadata['quality_signals'] : [];
        $acoustic = is_array($metadata['acoustic_analysis'] ?? null) ? $metadata['acoustic_analysis'] : [];
        $reasons = [];
        $severity = 'warning';

        if ($evaluation->status === Evaluation::STATUS_AI_FAILED) {
            $severity = 'critical';
            $reasons[] = 'la evaluacion IA fallo';
        }

        if ($evaluation->percentage_score !== null && (float) $evaluation->percentage_score < 70) {
            $severity = 'critical';
            $reasons[] = 'nota critica '.$this->formatPercent($evaluation->percentage_score);
        }

        if ($evaluation->status === Evaluation::STATUS_AGENT_DISPUTED) {
            $severity = $severity === 'critical' ? 'critical' : 'warning';
            $reasons[] = 'disputa del asesor';
        }

        $risk = strtolower((string) Arr::get($qualitySignals, 'customer_experience_risk', ''));
        if (in_array($risk, ['alto', 'high', 'critico', 'critical'], true)) {
            $severity = 'critical';
            $reasons[] = 'riesgo alto de experiencia';
        }

        if (filter_var(Arr::get($qualitySignals, 'customer_left_unresolved'), FILTER_VALIDATE_BOOLEAN)) {
            $severity = 'critical';
            $reasons[] = 'cliente queda sin resolver';
        }

        if ($reasons !== []) {
            $alerts->push($this->evaluationAlert(
                $evaluation,
                $severity,
                'Revisar evaluacion de calidad',
                'Detectado: '.implode(', ', array_unique($reasons)).'.'
            ));
        }

        $deadAirSeconds = (float) Arr::get($acoustic, 'dead_air_total_seconds', 0);
        if ($deadAirSeconds >= 30) {
            $alerts->push($this->evaluationAlert(
                $evaluation,
                'warning',
                'Tiempo muerto relevante',
                'El audio acumula '.$this->formatDuration($deadAirSeconds).' de silencio operativo.'
            ));
        }

        return $alerts;
    }

    private function evaluationAlert(Evaluation $evaluation, string $severity, string $title, string $description): array
    {
        return [
            'id' => 'evaluation-'.$evaluation->id.'-'.str($title)->slug(),
            'type' => 'evaluation',
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'evaluation_id' => $evaluation->id,
            'score' => $evaluation->percentage_score !== null ? (float) $evaluation->percentage_score : null,
            'status' => $evaluation->status,
            'status_label' => Evaluation::statusLabel($evaluation->status),
            'campaign' => $evaluation->campaign?->displayName(),
            'campaign_id' => $evaluation->campaign_id,
            'campaign_parent' => $evaluation->campaign?->parent?->name,
            'subcampaign' => $evaluation->campaign?->parent ? $evaluation->campaign->name : null,
            'agent' => $evaluation->agent?->name,
            'created_at' => $evaluation->created_at?->toIso8601String(),
            'action_url' => url('/evaluations/'.$evaluation->id),
        ];
    }

    private function evaluationPayload(Evaluation $evaluation): array
    {
        $metadata = $evaluation->interaction?->metadata ?? [];
        $qualitySignals = is_array($metadata['quality_signals'] ?? null) ? $metadata['quality_signals'] : [];
        $acoustic = is_array($metadata['acoustic_analysis'] ?? null) ? $metadata['acoustic_analysis'] : [];

        return [
            'id' => $evaluation->id,
            'type' => $evaluation->type,
            'score' => $evaluation->percentage_score !== null ? (float) $evaluation->percentage_score : null,
            'score_label' => $evaluation->percentage_score !== null ? $this->formatPercent($evaluation->percentage_score) : 'Sin nota',
            'status' => $evaluation->status,
            'status_label' => Evaluation::statusLabel($evaluation->status),
            'campaign' => $evaluation->campaign?->displayName(),
            'campaign_id' => $evaluation->campaign_id,
            'campaign_parent' => $evaluation->campaign?->parent?->name,
            'subcampaign' => $evaluation->campaign?->parent ? $evaluation->campaign->name : null,
            'agent' => $evaluation->agent?->name,
            'evaluator' => $evaluation->evaluator?->name,
            'created_at' => $evaluation->created_at?->toIso8601String(),
            'occurred_at' => $evaluation->interaction?->occurred_at?->toIso8601String(),
            'agent_viewed_at' => $evaluation->agent_viewed_at?->toIso8601String(),
            'visible_to_agent_at' => $evaluation->visible_to_agent_at?->toIso8601String(),
            'audio' => [
                'duration_seconds' => $evaluation->interaction?->audio_duration,
                'source_type' => $evaluation->interaction?->source_type,
                'dead_air_seconds' => (float) Arr::get($acoustic, 'dead_air_total_seconds', 0),
                'dead_air_label' => Arr::get($acoustic, 'dead_air_total_label'),
                'silence_ratio' => (float) Arr::get($acoustic, 'silence_ratio', 0),
            ],
            'feedback_indicators' => [
                'empathy' => Arr::get($qualitySignals, 'empathy'),
                'active_listening' => Arr::get($qualitySignals, 'active_listening'),
                'objection_handling' => Arr::get($qualitySignals, 'objection_handling'),
                'resolution_clarity' => Arr::get($qualitySignals, 'resolution_clarity'),
                'speech_control' => Arr::get($qualitySignals, 'speech_control'),
                'customer_left_unresolved' => Arr::get($qualitySignals, 'customer_left_unresolved'),
                'customer_experience_risk' => Arr::get($qualitySignals, 'customer_experience_risk'),
            ],
            'feedback_response' => [
                'responded' => (bool) $evaluation->agentResponse,
                'type' => $evaluation->agentResponse?->response_type,
                'responded_at' => $evaluation->agentResponse?->responded_at?->toIso8601String(),
                'commitment_comment' => $evaluation->agentResponse?->commitment_comment,
                'dispute_reason' => $evaluation->agentResponse?->dispute_reason,
            ],
            'feedback_audio' => [
                'status' => $evaluation->feedback_audio_status,
                'ready' => $evaluation->feedback_audio_status === 'ready' && (bool) $evaluation->feedback_audio_path,
                'generated_at' => $evaluation->feedback_audio_generated_at?->toIso8601String(),
                'url' => $evaluation->feedback_audio_status === 'ready' && $evaluation->feedback_audio_path
                    ? url('/api/mobile/evaluations/'.$evaluation->id.'/feedback-audio')
                    : null,
            ],
            'summary' => $evaluation->ai_summary,
            'review_notes' => $evaluation->review_notes,
            'action_url' => url('/evaluations/'.$evaluation->id),
            'items' => $evaluation->items->map(fn ($item) => [
                'id' => $item->id,
                'status' => $item->status,
                'confidence' => $item->confidence,
                'evidence_quote' => $item->evidence_quote,
                'evidence_reference' => $item->evidence_reference,
                'ai_notes' => $item->ai_notes,
                'subattribute' => [
                    'id' => $item->subAttribute?->id,
                    'name' => $item->subAttribute?->name,
                    'weight_percent' => $item->subAttribute?->weight_percent,
                    'attribute_name' => $item->subAttribute?->attribute?->name,
                ]
            ])->values(),
            'conversation_turns' => $evaluation->interaction && $evaluation->interaction->transcript_text 
                ? (new \App\Support\TranscriptConversationParser())->parse($evaluation->interaction->transcript_text)
                : [],
            'audio_url' => $evaluation->interaction && $evaluation->interaction->isAudio()
                ? url('/api/mobile/transcripts/'.$evaluation->interaction->id.'/audio')
                : null,
            'source_type' => $evaluation->interaction?->source_type,
            'file_name' => $evaluation->interaction?->file_name,
        ];
    }

    private function formatPercent($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.').'%';
    }

    private function formatDuration(float $seconds): string
    {
        $seconds = max(0, (int) round($seconds));
        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }

    private function levelName(float $score): string
    {
        return match (true) {
            $score >= 90 => 'Q1 - Diamante',
            $score >= 80 => 'Q2 - Oro',
            $score >= 70 => 'Q3 - Plata',
            default => 'Q4 - Bronce',
        };
    }
}
