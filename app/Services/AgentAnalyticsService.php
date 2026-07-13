<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignUserAssignment;
use App\Models\Evaluation;
use Illuminate\Support\Carbon;

class AgentAnalyticsService
{
    public function __construct(private QualityAnalyticsService $analytics) {}

    public function getAgentRanking(array $filters = [], int $limit = 20): array
    {
        $query = Evaluation::query();

        $user = auth()->user();
        $agentIds = null;

        if ($user && $user->hasRole('agent')) {
            $supervisorIds = CampaignUserAssignment::where('agent_id', $user->id)
                ->where('is_active', true)
                ->pluck('supervisor_id');

            $agentIds = CampaignUserAssignment::whereIn('supervisor_id', $supervisorIds)
                ->where('is_active', true)
                ->pluck('agent_id')
                ->push($user->id)
                ->unique();
        }

        if ($agentIds) {
            $query->finalForReporting();

            if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
                $query->whereBetween('evaluations.created_at', [
                    Carbon::parse($filters['start_date'])->startOfDay(),
                    Carbon::parse($filters['end_date'])->endOfDay(),
                ]);
            }

            if (! empty($filters['campaign_id'])) {
                $query->whereIn('evaluations.campaign_id', Campaign::idsForFilter($filters['campaign_id']));
            } elseif (! empty($filters['parent_campaign_id'])) {
                $query->whereIn('evaluations.campaign_id', Campaign::idsForFilter($filters['parent_campaign_id']));
            }

            if (! empty($filters['agent_id'])) {
                $query->where('evaluations.agent_id', $filters['agent_id']);
            } else {
                $query->whereIn('evaluations.agent_id', $agentIds);
            }
        } else {
            $this->analytics->applyFiltersPublic($query, $filters);
        }

        $rows = $query->join('users', 'evaluations.agent_id', '=', 'users.id')
            ->selectRaw('
                users.id,
                users.name as label,
                AVG(percentage_score) as avg_score,
                COUNT(*) as total_evals,
                SUM(CASE WHEN percentage_score >= 90 THEN 1 ELSE 0 END) as excellent,
                SUM(CASE WHEN percentage_score < 70 THEN 1 ELSE 0 END) as critical
            ')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('avg_score')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'label' => $item->label,
                'avg_score' => round((float) $item->avg_score, 2),
                'total_evals' => (int) $item->total_evals,
                'excellent' => (int) $item->excellent,
                'critical' => (int) $item->critical,
            ])
            ->toArray();

        return $this->analytics->withGroupedInsightsPublic($rows, 'agent', 'avg_score', 'total_evals');
    }

    public function getAgentLeague(float $averageScore): array
    {
        if ($averageScore >= 90) {
            return [
                'name' => 'Q1 - Diamante',
                'color' => 'text-cyan-500 dark:text-cyan-400',
                'bg' => 'bg-cyan-50 dark:bg-cyan-950/20',
                'border' => 'border-cyan-400',
                'icon' => '<svg class="w-16 h-16 text-cyan-500 dark:text-cyan-400 drop-shadow-[0_0_10px_rgba(34,211,238,0.4)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="diamond-grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#22d3ee" /><stop offset="50%" stop-color="#818cf8" /><stop offset="100%" stop-color="#c084fc" /></linearGradient></defs><path d="M6 3h12l4 6-10 12L2 9z" fill="url(#diamond-grad)" fill-opacity="0.15" stroke="url(#diamond-grad)" stroke-width="1.8"/><path d="M11 3 8 9l4 12 4-12-3-6" stroke="url(#diamond-grad)" stroke-width="1"/><path d="M2 9h20" stroke="url(#diamond-grad)" stroke-width="1"/></svg>',
            ];
        }
        if ($averageScore >= 80) {
            return [
                'name' => 'Q2 - Oro',
                'color' => 'text-amber-500 dark:text-amber-400',
                'bg' => 'bg-amber-50 dark:bg-amber-950/20',
                'border' => 'border-amber-400',
                'icon' => '<svg class="w-16 h-16 text-amber-500 dark:text-amber-400 drop-shadow-[0_0_10px_rgba(251,191,36,0.4)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="gold-grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#fbbf24" /><stop offset="100%" stop-color="#d97706" /></linearGradient></defs><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" fill="url(#gold-grad)" fill-opacity="0.15" stroke="url(#gold-grad)" stroke-width="1.8"/><circle cx="12" cy="11" r="4" stroke="url(#gold-grad)" stroke-width="1.5"/><path d="m12 9 1 2h2l-1.5 1.5.5 2-2-1-2 1 .5-2L9 11h2z" fill="url(#gold-grad)"/></svg>',
            ];
        }
        if ($averageScore >= 70) {
            return [
                'name' => 'Q3 - Plata',
                'color' => 'text-slate-500 dark:text-slate-400',
                'bg' => 'bg-slate-50 dark:bg-slate-950/20',
                'border' => 'border-slate-400',
                'icon' => '<svg class="w-16 h-16 text-slate-500 dark:text-slate-400 drop-shadow-[0_0_10px_rgba(148,163,184,0.3)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="silver-grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#cbd5e1" /><stop offset="50%" stop-color="#94a3b8" /><stop offset="100%" stop-color="#64748b" /></linearGradient></defs><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" fill="url(#silver-grad)" fill-opacity="0.15" stroke="url(#silver-grad)" stroke-width="1.8"/><path d="M12 8v8" stroke="url(#silver-grad)" stroke-width="1.5"/><path d="M9 11h6" stroke="url(#silver-grad)" stroke-width="1.5"/></svg>',
            ];
        }

        return [
            'name' => 'Q4 - Bronce',
            'color' => 'text-orange-600 dark:text-orange-400',
            'bg' => 'bg-orange-50 dark:bg-orange-950/20',
            'border' => 'border-orange-400',
            'icon' => '<svg class="w-16 h-16 text-orange-600 dark:text-orange-400 drop-shadow-[0_0_10px_rgba(249,115,22,0.4)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="bronze-grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#fdba74" /><stop offset="50%" stop-color="#f97316" /><stop offset="100%" stop-color="#ea580c" /></linearGradient></defs><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" fill="url(#bronze-grad)" fill-opacity="0.15" stroke="url(#bronze-grad)" stroke-width="1.8"/><path d="m12 8-3.5 6h7z" stroke="url(#bronze-grad)" stroke-width="1.5"/><circle cx="12" cy="13" r="0.5" fill="url(#bronze-grad)"/></svg>',
        ];
    }

    public function getAgentMatchHistory(array $filters = [], int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        $query = Evaluation::query()
            ->with(['campaign.parent', 'evaluator:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit);

        $this->analytics->applyFiltersPublic($query, $filters);

        return $query->get();
    }

    public function paginateAgentMatchHistory(array $filters = [], int $perPage = 5): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Evaluation::query()
            ->with(['campaign.parent', 'evaluator:id,name'])
            ->orderByDesc('created_at');

        $this->analytics->applyFiltersPublic($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }
}
