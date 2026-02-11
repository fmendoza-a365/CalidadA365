<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\User;
use App\Models\Campaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QualityAnalyticsService
{
    /**
     * Tab 1: Dashboard Calidad - Overview Stats
     */
    public function getOverviewStats(array $filters = []): array
    {
        $query = Evaluation::query();
        $this->applyFilters($query, $filters);

        $totalEvaluations = $query->count();
        $averageScore = (float) ($query->avg('percentage_score') ?? 0);

        // Nota sin MP%: Average score of evaluations that have NO critical failures
        $evalsWithMP = $this->getEvalsWithCriticalFailures($filters);
        $queryNoMP = Evaluation::query();
        $this->applyFilters($queryNoMP, $filters);
        if ($evalsWithMP->isNotEmpty()) {
            $queryNoMP->whereNotIn('id', $evalsWithMP);
        }
        $averageScoreNoMP = (float) ($queryNoMP->avg('percentage_score') ?? 0);

        // # Malas Prácticas
        $mpCount = $evalsWithMP->count();
        $mpPercentage = $totalEvaluations > 0 ? round(($mpCount / $totalEvaluations) * 100, 1) : 0;

        // Agentes activos (distintos evaluados)
        $activeAgents = Evaluation::query()->tap(fn($q) => $this->applyFilters($q, $filters))
            ->distinct('agent_id')->count('agent_id');

        // Monitores activos (distintos evaluadores)
        $activeMonitors = Evaluation::query()->tap(fn($q) => $this->applyFilters($q, $filters))
            ->whereNotNull('evaluator_id')
            ->distinct('evaluator_id')->count('evaluator_id');

        // Feedback realizado
        $feedbackDone = Evaluation::query()->tap(fn($q) => $this->applyFilters($q, $filters))
            ->whereNotNull('agent_viewed_at')->count();
        $feedbackPct = $totalEvaluations > 0 ? round(($feedbackDone / $totalEvaluations) * 100, 1) : 0;

        // Evaluaciones por agente promedio
        $evalsPerAgent = $activeAgents > 0 ? round($totalEvaluations / $activeAgents, 1) : 0;

        // Evaluaciones por monitor promedio
        $evalsPerMonitor = $activeMonitors > 0 ? round($totalEvaluations / $activeMonitors, 1) : 0;

        return [
            'total_evaluations' => $totalEvaluations,
            'average_score' => round($averageScore, 2),
            'average_score_no_mp' => round($averageScoreNoMP, 2),
            'mp_count' => $mpCount,
            'mp_percentage' => $mpPercentage,
            'active_agents' => $activeAgents,
            'active_monitors' => $activeMonitors,
            'feedback_done' => $feedbackDone,
            'feedback_percentage' => $feedbackPct,
            'evals_per_agent' => $evalsPerAgent,
            'evals_per_monitor' => $evalsPerMonitor,
        ];
    }

    /**
     * Get evaluation IDs that have at least one critical failure
     */
    private function getEvalsWithCriticalFailures(array $filters = [])
    {
        return EvaluationItem::whereHas('evaluation', function ($q) use ($filters) {
            $this->applyFilters($q, $filters);
        })
            ->whereHas('subAttribute', fn($q) => $q->where('is_critical', true))
            ->where('status', 'non_compliant')
            ->distinct()
            ->pluck('evaluation_id');
    }

    /**
     * Quality grouped by dimension (month, week, campaign, supervisor, daily)
     */
    public function getQualityGrouped(string $groupBy, array $filters = []): array
    {
        $query = Evaluation::query();
        $this->applyFilters($query, $filters);

        switch ($groupBy) {
            case 'month':
                $query->selectRaw("strftime('%Y-%m', evaluations.created_at) as label, AVG(percentage_score) as avg_score, COUNT(*) as count")
                    ->groupBy('label')->orderBy('label');
                break;
            case 'week':
                $query->selectRaw("'Sem. ' || strftime('%W', evaluations.created_at) as label, AVG(percentage_score) as avg_score, COUNT(*) as count")
                    ->groupBy('label')->orderByRaw("strftime('%W', evaluations.created_at)");
                break;
            case 'campaign':
                $query->join('campaigns', 'evaluations.campaign_id', '=', 'campaigns.id')
                    ->selectRaw("campaigns.name as label, AVG(percentage_score) as avg_score, COUNT(*) as count")
                    ->groupBy('campaigns.id', 'campaigns.name');
                break;
            case 'supervisor':
                $query->join('users', 'evaluations.evaluator_id', '=', 'users.id')
                    ->selectRaw("users.name as label, AVG(percentage_score) as avg_score, COUNT(*) as count")
                    ->groupBy('users.id', 'users.name')
                    ->orderByDesc('avg_score');
                break;
            case 'daily':
                $query->selectRaw("date(evaluations.created_at) as label, AVG(percentage_score) as avg_score, COUNT(*) as count")
                    ->groupBy('label')->orderBy('label');
                break;
            case 'agent':
                $query->join('users', 'evaluations.agent_id', '=', 'users.id')
                    ->selectRaw("users.name as label, AVG(percentage_score) as avg_score, COUNT(*) as count")
                    ->groupBy('users.id', 'users.name')
                    ->orderByDesc('avg_score');
                break;
        }

        return $query->get()->map(fn($item) => [
            'label' => $item->label,
            'avg_score' => round((float) $item->avg_score, 2),
            'count' => (int) $item->count,
        ])->toArray();
    }

    /**
     * Tab 2: Malas Prácticas grouped by dimension
     */
    public function getMPGrouped(string $groupBy, array $filters = []): array
    {
        $query = EvaluationItem::query()
            ->whereHas('evaluation', fn($q) => $this->applyFilters($q, $filters))
            ->whereHas('subAttribute', fn($q) => $q->where('is_critical', true))
            ->where('evaluation_items.status', 'non_compliant')
            ->join('evaluations', 'evaluation_items.evaluation_id', '=', 'evaluations.id');

        switch ($groupBy) {
            case 'month':
                $query->selectRaw("strftime('%Y-%m', evaluations.created_at) as label, COUNT(*) as count")
                    ->groupBy('label')->orderBy('label');
                break;
            case 'week':
                $query->selectRaw("'Sem. ' || strftime('%W', evaluations.created_at) as label, COUNT(*) as count")
                    ->groupBy('label')->orderByRaw("strftime('%W', evaluations.created_at)");
                break;
            case 'campaign':
                $query->join('campaigns', 'evaluations.campaign_id', '=', 'campaigns.id')
                    ->selectRaw("campaigns.name as label, COUNT(*) as count")
                    ->groupBy('campaigns.id', 'campaigns.name');
                break;
            case 'supervisor':
                $query->join('users', 'evaluations.evaluator_id', '=', 'users.id')
                    ->selectRaw("users.name as label, COUNT(*) as count")
                    ->groupBy('users.id', 'users.name')
                    ->orderByDesc('count');
                break;
            case 'daily':
                $query->selectRaw("date(evaluations.created_at) as label, COUNT(*) as count")
                    ->groupBy('label')->orderBy('label');
                break;
            case 'agent':
                $query->join('users', 'evaluations.agent_id', '=', 'users.id')
                    ->selectRaw("users.name as label, COUNT(*) as count")
                    ->groupBy('users.id', 'users.name')
                    ->orderByDesc('count');
                break;
        }

        return $query->get()->map(fn($item) => [
            'label' => $item->label,
            'count' => (int) $item->count,
        ])->toArray();
    }

    /**
     * Top defects: which specific criteria fail the most
     */
    public function getTopDefects(array $filters = [], int $limit = 10): array
    {
        return EvaluationItem::whereHas('evaluation', fn($q) => $this->applyFilters($q, $filters))
            ->where('status', 'non_compliant')
            ->join('quality_subattributes', 'evaluation_items.subattribute_id', '=', 'quality_subattributes.id')
            ->select('quality_subattributes.name as label', DB::raw('COUNT(*) as count'), 'quality_subattributes.is_critical')
            ->groupBy('quality_subattributes.id', 'quality_subattributes.name', 'quality_subattributes.is_critical')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Tab 3: Feedback Stats
     */
    public function getFeedbackStats(array $filters = []): array
    {
        $query = Evaluation::query();
        $this->applyFilters($query, $filters);

        $total = $query->count();
        $done = (clone $query)->whereNotNull('agent_viewed_at')->count();
        $pending = $total - $done;

        return [
            'total' => $total,
            'done' => $done,
            'done_pct' => $total > 0 ? round(($done / $total) * 100, 1) : 0,
            'pending' => $pending,
            'pending_pct' => $total > 0 ? round(($pending / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Feedback grouped by supervisor
     */
    public function getFeedbackBySupervisor(array $filters = []): array
    {
        return Evaluation::query()
            ->tap(fn($q) => $this->applyFilters($q, $filters))
            ->whereNotNull('evaluator_id')
            ->join('users', 'evaluations.evaluator_id', '=', 'users.id')
            ->selectRaw("users.name as label, COUNT(*) as total, SUM(CASE WHEN agent_viewed_at IS NOT NULL THEN 1 ELSE 0 END) as done")
            ->groupBy('users.id', 'users.name')
            ->get()
            ->map(fn($item) => [
                'label' => $item->label,
                'total' => (int) $item->total,
                'done' => (int) $item->done,
                'pending' => (int) $item->total - (int) $item->done,
                'done_pct' => $item->total > 0 ? round(($item->done / $item->total) * 100, 1) : 0,
            ])
            ->toArray();
    }

    /**
     * Feedback grouped by week
     */
    public function getFeedbackByWeek(array $filters = []): array
    {
        return Evaluation::query()
            ->tap(fn($q) => $this->applyFilters($q, $filters))
            ->selectRaw("'Sem. ' || strftime('%W', evaluations.created_at) as label, COUNT(*) as total, SUM(CASE WHEN agent_viewed_at IS NOT NULL THEN 1 ELSE 0 END) as done")
            ->groupByRaw("strftime('%W', evaluations.created_at)")
            ->orderByRaw("strftime('%W', evaluations.created_at)")
            ->get()
            ->map(fn($item) => [
                'label' => $item->label,
                'total' => (int) $item->total,
                'done' => (int) $item->done,
                'done_pct' => $item->total > 0 ? round(($item->done / $item->total) * 100, 1) : 0,
            ])
            ->toArray();
    }

    /**
     * Agent ranking with detailed metrics
     */
    public function getAgentRanking(array $filters = [], int $limit = 20): array
    {
        $query = Evaluation::query();
        $this->applyFilters($query, $filters);

        return $query->join('users', 'evaluations.agent_id', '=', 'users.id')
            ->selectRaw("
                users.id,
                users.name as label,
                AVG(percentage_score) as avg_score,
                COUNT(*) as total_evals,
                SUM(CASE WHEN percentage_score >= 90 THEN 1 ELSE 0 END) as excellent,
                SUM(CASE WHEN percentage_score < 70 THEN 1 ELSE 0 END) as critical
            ")
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('avg_score')
            ->limit($limit)
            ->get()
            ->map(fn($item) => [
                'label' => $item->label,
                'avg_score' => round((float) $item->avg_score, 2),
                'total_evals' => (int) $item->total_evals,
                'excellent' => (int) $item->excellent,
                'critical' => (int) $item->critical,
            ])
            ->toArray();
    }

    /**
     * Tab 4/5: Quality by campaign (evaluations per campaign as horizontal bar)
     */
    public function getEvalsByCampaign(array $filters = []): array
    {
        $query = Evaluation::query();
        $this->applyFilters($query, $filters);

        return $query->join('campaigns', 'evaluations.campaign_id', '=', 'campaigns.id')
            ->selectRaw("campaigns.name as label, COUNT(*) as count")
            ->groupBy('campaigns.id', 'campaigns.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn($item) => [
                'label' => $item->label,
                'count' => (int) $item->count,
            ])
            ->toArray();
    }

    /**
     * Apply common query filters
     */
    protected function applyFilters($query, array $filters): void
    {
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('evaluations.created_at', [
                Carbon::parse($filters['start_date'])->startOfDay(),
                Carbon::parse($filters['end_date'])->endOfDay()
            ]);
        }

        if (!empty($filters['campaign_id'])) {
            $query->where('evaluations.campaign_id', $filters['campaign_id']);
        }

        if (!empty($filters['agent_id'])) {
            $query->where('evaluations.agent_id', $filters['agent_id']);
        }
    }
}
