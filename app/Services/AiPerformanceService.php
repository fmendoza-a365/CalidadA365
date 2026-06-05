<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Collection;

class AiPerformanceService
{
    public function summary(array $filters, User $user): array
    {
        $evaluations = $this->query($filters, $user)->get();
        $total = $evaluations->count();
        $failed = $evaluations->where('status', Evaluation::STATUS_AI_FAILED)->count();
        $published = $evaluations->whereIn('status', [
            Evaluation::STATUS_PUBLISHED_TO_AGENT,
            Evaluation::STATUS_AGENT_ACCEPTED,
            Evaluation::STATUS_AGENT_DISPUTED,
            Evaluation::STATUS_DISPUTE_RESOLVED,
            Evaluation::STATUS_CLOSED,
        ])->count();

        return [
            'total_ai_evaluations' => $total,
            'failed_ai_evaluations' => $failed,
            'published_ai_evaluations' => $published,
            'failed_rate' => $total > 0 ? round(($failed / $total) * 100, 1) : 0,
            'published_rate' => $total > 0 ? round(($published / $total) * 100, 1) : 0,
            'average_score' => round((float) $evaluations->whereNotNull('percentage_score')->avg('percentage_score'), 2),
            'provider_rows' => $this->groupRows($evaluations, 'ai_provider'),
            'model_rows' => $this->groupRows($evaluations, 'ai_model'),
            'prompt_rows' => $this->groupRows($evaluations, 'ai_prompt_version'),
        ];
    }

    private function query(array $filters, User $user)
    {
        $query = Evaluation::query()
            ->forUser($user)
            ->where('type', 'ai')
            ->whereNotNull('ai_processed_at');

        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $query->whereBetween('evaluations.created_at', [
                $filters['start_date'].' 00:00:00',
                $filters['end_date'].' 23:59:59',
            ]);
        }

        if (! empty($filters['campaign_id'])) {
            $query->whereIn('campaign_id', Campaign::idsForFilter($filters['campaign_id']));
        }

        return $query;
    }

    private function groupRows(Collection $evaluations, string $key): array
    {
        return $evaluations
            ->groupBy(fn (Evaluation $evaluation) => $evaluation->{$key} ?: 'N/A')
            ->map(function (Collection $rows, string $label) {
                $failed = $rows->where('status', Evaluation::STATUS_AI_FAILED)->count();

                return [
                    'label' => $label,
                    'count' => $rows->count(),
                    'avg_score' => round((float) $rows->whereNotNull('percentage_score')->avg('percentage_score'), 2),
                    'failed' => $failed,
                    'failed_rate' => $rows->count() > 0 ? round(($failed / $rows->count()) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }
}
