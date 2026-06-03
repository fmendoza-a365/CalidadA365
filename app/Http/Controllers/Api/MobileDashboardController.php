<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class MobileDashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
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

        return response()->json([
            'summary' => $summary,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min(max((int) $request->query('limit', 20), 1), 50);

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

        $payload = $alerts
            ->sortByDesc(fn (array $alert) => $alert['created_at'] ?? '')
            ->values()
            ->take($limit)
            ->values();

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
}
