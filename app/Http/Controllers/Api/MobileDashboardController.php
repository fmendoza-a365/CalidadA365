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
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
        $ranking = collect($this->analytics->getAgentRanking($filters, 8))
            ->map(fn (array $agent, int $index) => [
                ...$agent,
                'position' => $index + 1,
                'score_label' => $this->formatPercent($agent['avg_score'] ?? 0),
                'level' => $this->levelName((float) ($agent['avg_score'] ?? 0)),
            ])
            ->values();

        $recentEvaluations = $this->visibleEvaluations($user)
            ->with(['agentResponse', 'agent', 'campaign', 'interaction', 'evaluator'])
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
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->values(),
                'primary_view' => $user->hasRole('agent') ? 'agent' : 'executive',
                'avatar_url' => $user->avatar_url,
            ],
            'filters' => $filters,
            'summary' => $summary,
            'overview' => $overview,
            'feedback' => $feedback,
            'quality_trend' => collect($this->analytics->getQualityGrouped('daily', $filters))->take(-7)->values(),
            'campaigns' => collect($this->analytics->getQualityGrouped('campaign', $filters))->take(6)->values(),
            'top_defects' => collect($this->analytics->getTopDefects($filters, 6))->values(),
            'ranking' => $ranking,
            'alerts' => $alerts,
            'evaluations' => $recentEvaluations,
            'modules' => $this->modulesPayload($user, $filters),
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
            ->with(['agentResponse', 'agent', 'campaign', 'interaction', 'evaluator'])
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

    public function markEvaluationViewed(Request $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorize('view', $evaluation);

        if ($request->user()->hasRole('agent') && $evaluation->isVisibleToAgent() && ! $evaluation->agent_viewed_at) {
            $evaluation->forceFill(['agent_viewed_at' => now()])->save();
        }

        return response()->json([
            'evaluation' => $this->evaluationPayload($evaluation->fresh(['agentResponse', 'agent', 'campaign', 'interaction', 'evaluator'])),
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
            'response_type' => ['required', 'in:accept,dispute'],
            'commitment_comment' => ['required_if:response_type,accept', 'nullable', 'string', 'max:5000'],
            'dispute_reason' => ['required_if:response_type,dispute', 'nullable', 'string', 'max:5000'],
            'disputed_items' => ['nullable', 'array'],
        ]);

        $responses->store($evaluation, $request->user(), $validated);

        return response()->json([
            'message' => 'Respuesta registrada.',
            'evaluation' => $this->evaluationPayload($evaluation->fresh(['agentResponse', 'agent', 'campaign', 'interaction', 'evaluator'])),
        ]);
    }

    public function transcriptAudio(Request $request, Interaction $interaction, TranscriptController $transcripts): Response
    {
        if (! Interaction::query()->forUser($request->user())->whereKey($interaction->id)->exists()) {
            abort(403, 'No tiene permiso para escuchar esta transcripcion.');
        }

        return $transcripts->audio($request, $interaction);
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
        ];
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
            ->with(['agent', 'campaign', 'interaction'])
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
            ->with(['agent', 'campaign', 'interaction'])
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
                    ->with(['agentResponse', 'agent', 'campaign', 'interaction', 'evaluator'])
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
        return $this->visibleInteractions($user)
            ->with(['agent', 'campaign', 'supervisor', 'evaluation'])
            ->latest('occurred_at')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (Interaction $interaction) => [
                'id' => $interaction->id,
                'campaign' => $interaction->campaign?->name,
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
            ->withCount(['interactions', 'forms'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (Campaign $campaign) use ($user, $filters) {
                $query = Evaluation::query()->forUser($user)->where('campaign_id', $campaign->id);
                $this->applyDateFilters($query, $filters);
                $total = (clone $query)->count();
                $average = (float) ((clone $query)->avg('percentage_score') ?? 0);

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'active' => (bool) $campaign->is_active,
                    'target_quality' => $campaign->target_quality !== null ? (float) $campaign->target_quality : null,
                    'evaluations' => $total,
                    'average_score' => round($average, 1),
                    'score_label' => $this->formatPercent($average),
                    'interactions' => (int) $campaign->interactions_count,
                    'forms' => (int) $campaign->forms_count,
                    'url' => url('/campaigns/'.$campaign->id),
                ];
            })
            ->values();
    }

    private function qualityFormsPayload(User $user): Collection
    {
        return QualityForm::forUser($user)
            ->with(['campaign', 'latestVersion'])
            ->withCount('versions')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (QualityForm $form) => [
                'id' => $form->id,
                'name' => $form->name,
                'campaign' => $form->campaign?->name,
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
            ->with('campaign')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (InsightReport $insight) => [
                'id' => $insight->id,
                'type' => $insight->type,
                'campaign' => $insight->campaign?->name,
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
            'campaign' => $evaluation->campaign?->name,
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
            'campaign' => $evaluation->campaign?->name,
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
            ],
            'summary' => $evaluation->ai_summary,
            'action_url' => url('/evaluations/'.$evaluation->id),
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
            $score >= 95 => 'Superior',
            $score >= 90 => 'Excelente',
            $score >= 80 => 'Solido',
            $score >= 70 => 'En seguimiento',
            default => 'Critico',
        };
    }
}
