<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\User;
use App\Services\QualityAnalyticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

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
            ->with(['agent', 'campaign', 'interaction', 'evaluator'])
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
            ->with(['agent', 'campaign', 'interaction', 'evaluator'])
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
