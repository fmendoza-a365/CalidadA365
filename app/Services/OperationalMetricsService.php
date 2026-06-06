<?php

namespace App\Services;

use App\Models\DisputeResolution;
use App\Models\Evaluation;
use App\Models\Campaign;
use App\Models\User;

class OperationalMetricsService
{
    public function summary(array $filters, User $user): array
    {
        $evaluations = Evaluation::query()
            ->forUser($user)
            ->with('evaluator:id,name,paternal_surname,maternal_surname')
            ->when(! empty($filters['start_date']) && ! empty($filters['end_date']), function ($query) use ($filters) {
                $query->whereBetween('evaluations.created_at', [
                    $filters['start_date'].' 00:00:00',
                    $filters['end_date'].' 23:59:59',
                ]);
            })
            ->when(! empty($filters['campaign_id']), fn ($query) => $query->whereIn('campaign_id', Campaign::idsForFilter($filters['campaign_id'])))
            ->get();

        $published = $evaluations->whereNotNull('visible_to_agent_at');
        $resolvedDisputes = DisputeResolution::query()
            ->whereHas('evaluation', fn ($query) => $query->forUser($user))
            ->whereNotNull('resolved_at')
            ->with('evaluation:id,created_at')
            ->get();

        return [
            'avg_hours_to_publish' => $this->averageHours($published, 'created_at', 'visible_to_agent_at'),
            'avg_hours_to_resolve_dispute' => $this->averageHours($resolvedDisputes, 'created_at', 'resolved_at'),
            'manual_evaluations' => $evaluations->where('type', 'manual')->count(),
            'ai_evaluations' => $evaluations->where('type', 'ai')->count(),
            'closed_evaluations' => $evaluations->where('status', Evaluation::STATUS_CLOSED)->count(),
            'monitor_rows' => $evaluations
                ->whereNotNull('evaluator_id')
                ->groupBy('evaluator_id')
                ->map(fn ($rows) => [
                    'name' => $rows->first()->evaluator?->full_name ?? 'N/A',
                    'count' => $rows->count(),
                    'avg_score' => round((float) $rows->whereNotNull('percentage_score')->avg('percentage_score'), 2),
                ])
                ->sortByDesc('count')
                ->values()
                ->all(),
        ];
    }

    private function averageHours($rows, string $startField, string $endField): float
    {
        $durations = collect($rows)
            ->map(function ($row) use ($startField, $endField) {
                $start = $row->{$startField};
                $end = $row->{$endField};

                if (! $start || ! $end) {
                    return null;
                }

                return $start->diffInMinutes($end) / 60;
            })
            ->filter(fn ($value) => $value !== null);

        return round((float) $durations->avg(), 2);
    }
}
