<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\Interaction;
use App\Models\User;
use App\Models\Campaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
                $query->selectRaw("{$this->getDateFormatSql('evaluations.created_at', 'month')} as label, AVG(percentage_score) as avg_score, COUNT(*) as count")
                    ->groupBy('label')->orderBy('label');
                break;
            case 'week':
                $weekBucket = $this->getWeekOfMonthSql('evaluations.created_at');
                $query->selectRaw("$weekBucket as week_bucket, AVG(percentage_score) as avg_score, COUNT(*) as count")
                    ->groupByRaw($weekBucket)->orderByRaw($weekBucket);
                break;
            case 'campaign':
                $campaignLabel = $this->campaignLabelSql('campaigns', 'parent_campaigns');

                $query->join('campaigns', 'evaluations.campaign_id', '=', 'campaigns.id')
                    ->leftJoin('campaigns as parent_campaigns', 'campaigns.parent_id', '=', 'parent_campaigns.id')
                    ->selectRaw("$campaignLabel as label, AVG(percentage_score) as avg_score, COUNT(*) as count")
                    ->groupBy('campaigns.id', 'campaigns.name', 'parent_campaigns.id', 'parent_campaigns.name');
                break;
            case 'supervisor':
                $query->join('users as agents', 'evaluations.agent_id', '=', 'agents.id')
                    ->leftJoin('users as supervisors', 'agents.supervisor_id', '=', 'supervisors.id')
                    ->selectRaw("COALESCE(supervisors.name, 'Sin Supervisor') as label, AVG(percentage_score) as avg_score, COUNT(*) as count")
                    ->groupBy('supervisors.id', 'supervisors.name')
                    ->orderByDesc('avg_score');
                break;
            case 'daily':
                $query->selectRaw("{$this->getDateFormatSql('evaluations.created_at', 'day')} as label, AVG(percentage_score) as avg_score, COUNT(*) as count")
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
            'label' => $groupBy === 'week' ? 'Semana ' . (int) $item->week_bucket : $item->label,
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
                $query->selectRaw("{$this->getDateFormatSql('evaluations.created_at', 'month')} as label, COUNT(*) as count")
                    ->groupBy('label')->orderBy('label');
                break;
            case 'week':
                $weekBucket = $this->getWeekOfMonthSql('evaluations.created_at');
                $query->selectRaw("$weekBucket as week_bucket, COUNT(*) as count")
                    ->groupByRaw($weekBucket)->orderByRaw($weekBucket);
                break;
            case 'campaign':
                $campaignLabel = $this->campaignLabelSql('campaigns', 'parent_campaigns');

                $query->join('campaigns', 'evaluations.campaign_id', '=', 'campaigns.id')
                    ->leftJoin('campaigns as parent_campaigns', 'campaigns.parent_id', '=', 'parent_campaigns.id')
                    ->selectRaw("$campaignLabel as label, COUNT(*) as count")
                    ->groupBy('campaigns.id', 'campaigns.name', 'parent_campaigns.id', 'parent_campaigns.name');
                break;
            case 'supervisor':
                $query->join('users as agents', 'evaluations.agent_id', '=', 'agents.id')
                    ->leftJoin('users as supervisors', 'agents.supervisor_id', '=', 'supervisors.id')
                    ->selectRaw("COALESCE(supervisors.name, 'Sin Supervisor') as label, COUNT(*) as count")
                    ->groupBy('supervisors.id', 'supervisors.name')
                    ->orderByDesc('count');
                break;
            case 'daily':
                $query->selectRaw("{$this->getDateFormatSql('evaluations.created_at', 'day')} as label, COUNT(*) as count")
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
            'label' => $groupBy === 'week' ? 'Semana ' . (int) $item->week_bucket : $item->label,
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
            ->join('users as agents', 'evaluations.agent_id', '=', 'agents.id')
            ->leftJoin('users as supervisors', 'agents.supervisor_id', '=', 'supervisors.id')
            ->selectRaw("COALESCE(supervisors.name, 'Sin Supervisor') as label, COUNT(*) as total, SUM(CASE WHEN agent_viewed_at IS NOT NULL THEN 1 ELSE 0 END) as done")
            ->groupBy('supervisors.id', 'supervisors.name')
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
        $weekBucket = $this->getWeekOfMonthSql('evaluations.created_at');
        return Evaluation::query()
            ->tap(fn($q) => $this->applyFilters($q, $filters))
            ->selectRaw("$weekBucket as week_bucket, COUNT(*) as total, SUM(CASE WHEN agent_viewed_at IS NOT NULL THEN 1 ELSE 0 END) as done")
            ->groupByRaw($weekBucket)
            ->orderByRaw($weekBucket)
            ->get()
            ->map(fn($item) => [
                'label' => 'Semana ' . (int) $item->week_bucket,
                'total' => (int) $item->total,
                'done' => (int) $item->done,
                'done_pct' => $item->total > 0 ? round(($item->done / $item->total) * 100, 1) : 0,
            ])
            ->toArray();
    }

    public function getFeedbackByPeriod(string $period, array $filters = []): array
    {
        $bucket = $this->getPeriodBucketSql('evaluations.created_at', $period);

        return Evaluation::query()
            ->tap(fn($q) => $this->applyFilters($q, $filters))
            ->selectRaw("$bucket as bucket, COUNT(*) as total, SUM(CASE WHEN agent_viewed_at IS NOT NULL THEN 1 ELSE 0 END) as done")
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->get()
            ->map(fn($item) => [
                'label' => $this->periodLabel((string) $item->bucket, $period),
                'total' => (int) $item->total,
                'done' => (int) $item->done,
                'pending' => (int) $item->total - (int) $item->done,
                'done_pct' => $item->total > 0 ? round(($item->done / $item->total) * 100, 1) : 0,
            ])
            ->toArray();
    }

    public function getQualityTrendSeries(array $filters = []): array
    {
        return [
            'day' => $this->getQualityGrouped('daily', $filters),
            'week' => $this->getQualityGrouped('week', $filters),
            'month' => $this->getQualityGrouped('month', $filters),
        ];
    }

    public function getMpTrendSeries(array $filters = []): array
    {
        return [
            'day' => $this->getMPGrouped('daily', $filters),
            'week' => $this->getMPGrouped('week', $filters),
            'month' => $this->getMPGrouped('month', $filters),
        ];
    }

    public function getFeedbackTrendSeries(array $filters = []): array
    {
        return [
            'day' => $this->getFeedbackByPeriod('day', $filters),
            'week' => $this->getFeedbackByPeriod('week', $filters),
            'month' => $this->getFeedbackByPeriod('month', $filters),
        ];
    }

    public function getAudioUploadPerformance(array $filters = [], int $recentLimit = 10): array
    {
        $query = Interaction::query()
            ->with([
                'uploadedBy:id,name,username',
                'campaign:id,parent_id,name',
                'campaign.parent:id,name',
                'aiEvaluation:id,interaction_id,status,ai_processed_at,reviewed_at,visible_to_agent_at',
            ])
            ->where('source_type', 'audio');

        $this->applyInteractionFilters($query, $filters);

        $interactions = $query
            ->orderBy('uploaded_by')
            ->orderBy('uploaded_at')
            ->orderBy('id')
            ->get();

        $monitorRows = $interactions
            ->groupBy('uploaded_by')
            ->map(function (Collection $items) {
                $items = $items->values();
                $gaps = collect();

                $items->each(function (Interaction $interaction, int $index) use ($items, $gaps) {
                    if ($index === 0) {
                        return;
                    }

                    $previous = $items[$index - 1];
                    $gap = $this->secondsBetween($previous->uploaded_at, $interaction->uploaded_at);

                    if ($gap !== null) {
                        $gaps->push($gap);
                    }
                });

                $aiDurations = $items
                    ->map(fn (Interaction $interaction) => $this->secondsBetween($interaction->uploaded_at, $interaction->aiEvaluation?->ai_processed_at))
                    ->filter(fn ($seconds) => $seconds !== null)
                    ->values();

                $reviewDurations = $items
                    ->map(fn (Interaction $interaction) => $this->secondsBetween($interaction->uploaded_at, $interaction->aiEvaluation?->reviewed_at))
                    ->filter(fn ($seconds) => $seconds !== null)
                    ->values();

                $transcriptionDurations = $items
                    ->map(fn (Interaction $interaction) => $this->secondsBetween(
                        $interaction->uploaded_at,
                        $this->metadataTimestamp($interaction, 'transcription_completed_at')
                    ))
                    ->filter(fn ($seconds) => $seconds !== null)
                    ->values();

                $monitor = $items->first()?->uploadedBy;

                return [
                    'id' => $monitor?->id,
                    'label' => $monitor?->name ?? 'Sin monitor',
                    'username' => $monitor?->username,
                    'audio_count' => $items->count(),
                    'avg_gap_seconds' => $this->average($gaps),
                    'avg_gap_label' => $this->formatSeconds($this->average($gaps)),
                    'max_gap_seconds' => $gaps->max(),
                    'max_gap_label' => $this->formatSeconds($gaps->max()),
                    'avg_transcription_seconds' => $this->average($transcriptionDurations),
                    'avg_transcription_label' => $this->formatSeconds($this->average($transcriptionDurations)),
                    'avg_ai_seconds' => $this->average($aiDurations),
                    'avg_ai_label' => $this->formatSeconds($this->average($aiDurations)),
                    'avg_review_seconds' => $this->average($reviewDurations),
                    'avg_review_label' => $this->formatSeconds($this->average($reviewDurations)),
                    'last_upload_at' => optional($items->last()?->uploaded_at)->toIso8601String(),
                ];
            })
            ->sortByDesc('audio_count')
            ->values();

        $allGaps = collect($monitorRows)->pluck('avg_gap_seconds')->filter(fn ($value) => $value !== null);
        $allAi = collect($monitorRows)->pluck('avg_ai_seconds')->filter(fn ($value) => $value !== null);
        $allReview = collect($monitorRows)->pluck('avg_review_seconds')->filter(fn ($value) => $value !== null);
        $allTranscription = collect($monitorRows)->pluck('avg_transcription_seconds')->filter(fn ($value) => $value !== null);

        $recent = $interactions
            ->sortByDesc('uploaded_at')
            ->take($recentLimit)
            ->values()
            ->map(function (Interaction $interaction) use ($interactions) {
                $previous = $interactions
                    ->where('uploaded_by', $interaction->uploaded_by)
                    ->where('uploaded_at', '<', $interaction->uploaded_at)
                    ->sortByDesc('uploaded_at')
                    ->first();

                $gap = $previous ? $this->secondsBetween($previous->uploaded_at, $interaction->uploaded_at) : null;
                $aiSeconds = $this->secondsBetween($interaction->uploaded_at, $interaction->aiEvaluation?->ai_processed_at);
                $reviewSeconds = $this->secondsBetween($interaction->uploaded_at, $interaction->aiEvaluation?->reviewed_at);

                return [
                    'id' => $interaction->id,
                    'monitor' => $interaction->uploadedBy?->name ?? 'Sin monitor',
                    'campaign' => $interaction->campaign?->displayName() ?? $interaction->campaign?->name ?? 'Sin campaña',
                    'file_name' => $interaction->file_name,
                    'uploaded_at' => optional($interaction->uploaded_at)->toIso8601String(),
                    'since_previous_seconds' => $gap,
                    'since_previous_label' => $this->formatSeconds($gap),
                    'upload_to_ai_seconds' => $aiSeconds,
                    'upload_to_ai_label' => $this->formatSeconds($aiSeconds),
                    'upload_to_review_seconds' => $reviewSeconds,
                    'upload_to_review_label' => $this->formatSeconds($reviewSeconds),
                    'status' => $interaction->transcription_status ?? $interaction->status,
                ];
            });

        return [
            'summary' => [
                'total_audio' => $interactions->count(),
                'monitors' => $monitorRows->count(),
                'avg_gap_seconds' => $this->average($allGaps),
                'avg_gap_label' => $this->formatSeconds($this->average($allGaps)),
                'avg_transcription_seconds' => $this->average($allTranscription),
                'avg_transcription_label' => $this->formatSeconds($this->average($allTranscription)),
                'avg_ai_seconds' => $this->average($allAi),
                'avg_ai_label' => $this->formatSeconds($this->average($allAi)),
                'avg_review_seconds' => $this->average($allReview),
                'avg_review_label' => $this->formatSeconds($this->average($allReview)),
            ],
            'by_monitor' => $monitorRows,
            'recent' => $recent,
        ];
    }

    /**
     * Agent ranking with detailed metrics
     */
    public function getAgentRanking(array $filters = [], int $limit = 20): array
    {
        $query = Evaluation::query();

        // 1. Determine whose evaluations to query for the ranking
        $user = auth()->user();
        $agentIds = null;

        if ($user && $user->hasRole('agent')) {
            // If the user is an agent, get all agents under their active supervisors
            $supervisorIds = \App\Models\CampaignUserAssignment::where('agent_id', $user->id)
                ->where('is_active', true)
                ->pluck('supervisor_id');

            $agentIds = \App\Models\CampaignUserAssignment::whereIn('supervisor_id', $supervisorIds)
                ->where('is_active', true)
                ->pluck('agent_id')
                ->push($user->id) // Always include themselves
                ->unique();
        }

        // Apply visual logic
        if ($agentIds) {
            // Explicitly set agent_ids to force the query to only consider these agents
            // We temporarily remove the global forUser scope's agent check by overriding it
            // The standard `applyFilters` will add `forUser`, which for agents restricts to `agent_id = $user->id`.
            // So we MUST NOT call `$this->applyFilters($query, $filters)` directly for this specific query
            // OR we customize `applyFilters` to not apply `forUser` if we are doing a team ranking.
            // Better approach: apply standard date/campaign filters, but handle `forUser` manually here.

            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                $query->whereBetween('evaluations.created_at', [
                    Carbon::parse($filters['start_date'])->startOfDay(),
                    Carbon::parse($filters['end_date'])->endOfDay()
                ]);
            }

            if (!empty($filters['campaign_id'])) {
                $query->whereIn('evaluations.campaign_id', Campaign::idsForFilter($filters['campaign_id']));
            }

            if (!empty($filters['agent_id'])) {
                $query->where('evaluations.agent_id', $filters['agent_id']);
            } else {
                $query->whereIn('evaluations.agent_id', $agentIds);
            }

        } else {
            // Normal apply filters for supervisors/admins
            $this->applyFilters($query, $filters);
        }

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
                'id' => $item->id,
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

        $campaignLabel = $this->campaignLabelSql('campaigns', 'parent_campaigns');

        return $query->join('campaigns', 'evaluations.campaign_id', '=', 'campaigns.id')
            ->leftJoin('campaigns as parent_campaigns', 'campaigns.parent_id', '=', 'parent_campaigns.id')
            ->selectRaw("$campaignLabel as label, COUNT(*) as count")
            ->groupBy('campaigns.id', 'campaigns.name', 'parent_campaigns.id', 'parent_campaigns.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn($item) => [
                'label' => $item->label,
                'count' => (int) $item->count,
            ])
            ->toArray();
    }

    /**
     * Helper to get date format SQL based on driver
     */
    private function getDateFormatSql(string $column, string $type): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return match ($type) {
                'month' => "strftime('%Y-%m', $column)",
                'week' => "strftime('%W', $column)",
                'day' => "date($column)",
                default => $column,
            };
        }

        // PostgreSQL
        return match ($type) {
            'month' => "TO_CHAR($column, 'YYYY-MM')",
            'week' => "TO_CHAR($column, 'IW')", // ISO week
            'day' => "TO_CHAR($column, 'YYYY-MM-DD')", // or $column::date
            default => $column,
        };
    }

    private function getWeekOfMonthSql(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "CAST(((CAST(strftime('%d', $column) AS INTEGER) + 6) / 7) AS INTEGER)";
        }

        if ($driver === 'mysql') {
            return "CEIL(DAY($column) / 7)";
        }

        return "CEIL(EXTRACT(DAY FROM $column)::numeric / 7)";
    }

    private function campaignLabelSql(string $campaignAlias = 'campaigns', string $parentAlias = 'parent_campaigns'): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            return "CASE WHEN {$parentAlias}.name IS NOT NULL THEN CONCAT({$parentAlias}.name, ' / ', {$campaignAlias}.name) ELSE {$campaignAlias}.name END";
        }

        return "CASE WHEN {$parentAlias}.name IS NOT NULL THEN {$parentAlias}.name || ' / ' || {$campaignAlias}.name ELSE {$campaignAlias}.name END";
    }

    private function getPeriodBucketSql(string $column, string $period): string
    {
        return match ($period) {
            'week' => $this->getWeekOfMonthSql($column),
            'month' => $this->getDateFormatSql($column, 'month'),
            default => $this->getDateFormatSql($column, 'day'),
        };
    }

    private function periodLabel(string $bucket, string $period): string
    {
        return match ($period) {
            'week' => 'Semana '.(int) $bucket,
            default => $bucket,
        };
    }

    /**
     * Apply common query filters
     */
    protected function applyFilters($query, array $filters): void
    {
        // Enforce user visibility scope globally to all analytics
        if (auth()->check()) {
            $query->forUser(auth()->user());
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            // Check if table joining is happening to avoid ambiguous columns
            // Some queries use raw Evaluation::query() which defaults to evaluations.*
            // We use evaluations.created_at to be safe because of joins in other methods.
            $query->whereBetween('evaluations.created_at', [
                Carbon::parse($filters['start_date'])->startOfDay(),
                Carbon::parse($filters['end_date'])->endOfDay()
            ]);
        }

        if (!empty($filters['campaign_id'])) {
            $query->whereIn('evaluations.campaign_id', Campaign::idsForFilter($filters['campaign_id']));
        }

        if (!empty($filters['agent_id'])) {
            $query->where('evaluations.agent_id', $filters['agent_id']);
        }
    }

    protected function applyInteractionFilters($query, array $filters): void
    {
        if (auth()->check()) {
            $query->forUser(auth()->user());
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('interactions.uploaded_at', [
                Carbon::parse($filters['start_date'])->startOfDay(),
                Carbon::parse($filters['end_date'])->endOfDay()
            ]);
        }

        if (!empty($filters['campaign_id'])) {
            $query->whereIn('interactions.campaign_id', Campaign::idsForFilter($filters['campaign_id']));
        }

        if (!empty($filters['agent_id'])) {
            $query->where('interactions.agent_id', $filters['agent_id']);
        }
    }

    private function secondsBetween($start, $end): ?int
    {
        if (! $start || ! $end) {
            return null;
        }

        return (int) max(0, Carbon::parse($start)->diffInSeconds(Carbon::parse($end), false));
    }

    private function metadataTimestamp(Interaction $interaction, string $key): ?string
    {
        $metadata = $interaction->metadata ?? [];
        $value = is_array($metadata) ? ($metadata[$key] ?? null) : null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function average($values): ?int
    {
        $collection = collect($values)->filter(fn ($value) => $value !== null);

        if ($collection->isEmpty()) {
            return null;
        }

        return (int) round($collection->avg());
    }

    private function formatSeconds(?int $seconds): string
    {
        if ($seconds === null) {
            return 'Sin datos';
        }

        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remaining = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes);
        }

        if ($minutes > 0) {
            return sprintf('%dm %02ds', $minutes, $remaining);
        }

        return sprintf('%ds', $remaining);
    }

    /**
     * Determines League (Elo) based on average score
     */
    public function getAgentLeague(float $averageScore): array
    {
        if ($averageScore >= 90) {
            return [
                'name' => 'Q1 - Diamante',
                'color' => 'text-cyan-500 dark:text-cyan-400',
                'bg' => 'bg-cyan-50 dark:bg-cyan-950/20',
                'border' => 'border-cyan-400',
                'icon' => '<svg class="w-16 h-16 text-cyan-500 dark:text-cyan-400 drop-shadow-[0_0_10px_rgba(34,211,238,0.4)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="diamond-grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#22d3ee" /><stop offset="50%" stop-color="#818cf8" /><stop offset="100%" stop-color="#c084fc" /></linearGradient></defs><path d="M6 3h12l4 6-10 12L2 9z" fill="url(#diamond-grad)" fill-opacity="0.15" stroke="url(#diamond-grad)" stroke-width="1.8"/><path d="M11 3 8 9l4 12 4-12-3-6" stroke="url(#diamond-grad)" stroke-width="1"/><path d="M2 9h20" stroke="url(#diamond-grad)" stroke-width="1"/></svg>'
            ];
        }
        if ($averageScore >= 80) {
            return [
                'name' => 'Q2 - Oro',
                'color' => 'text-amber-500 dark:text-amber-400',
                'bg' => 'bg-amber-50 dark:bg-amber-950/20',
                'border' => 'border-amber-400',
                'icon' => '<svg class="w-16 h-16 text-amber-500 dark:text-amber-400 drop-shadow-[0_0_10px_rgba(251,191,36,0.4)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="gold-grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#fbbf24" /><stop offset="100%" stop-color="#d97706" /></linearGradient></defs><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" fill="url(#gold-grad)" fill-opacity="0.15" stroke="url(#gold-grad)" stroke-width="1.8"/><circle cx="12" cy="11" r="4" stroke="url(#gold-grad)" stroke-width="1.5"/><path d="m12 9 1 2h2l-1.5 1.5.5 2-2-1-2 1 .5-2L9 11h2z" fill="url(#gold-grad)"/></svg>'
            ];
        }
        if ($averageScore >= 70) {
            return [
                'name' => 'Q3 - Plata',
                'color' => 'text-slate-500 dark:text-slate-400',
                'bg' => 'bg-slate-50 dark:bg-slate-950/20',
                'border' => 'border-slate-400',
                'icon' => '<svg class="w-16 h-16 text-slate-500 dark:text-slate-400 drop-shadow-[0_0_10px_rgba(148,163,184,0.3)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="silver-grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#cbd5e1" /><stop offset="50%" stop-color="#94a3b8" /><stop offset="100%" stop-color="#64748b" /></linearGradient></defs><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" fill="url(#silver-grad)" fill-opacity="0.15" stroke="url(#silver-grad)" stroke-width="1.8"/><path d="M12 8v8" stroke="url(#silver-grad)" stroke-width="1.5"/><path d="M9 11h6" stroke="url(#silver-grad)" stroke-width="1.5"/></svg>'
            ];
        }
        return [
            'name' => 'Q4 - Bronce',
            'color' => 'text-orange-600 dark:text-orange-400',
            'bg' => 'bg-orange-50 dark:bg-orange-950/20',
            'border' => 'border-orange-400',
            'icon' => '<svg class="w-16 h-16 text-orange-600 dark:text-orange-400 drop-shadow-[0_0_10px_rgba(249,115,22,0.4)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="bronze-grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#fdba74" /><stop offset="50%" stop-color="#f97316" /><stop offset="100%" stop-color="#ea580c" /></linearGradient></defs><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" fill="url(#bronze-grad)" fill-opacity="0.15" stroke="url(#bronze-grad)" stroke-width="1.8"/><path d="m12 8-3.5 6h7z" stroke="url(#bronze-grad)" stroke-width="1.5"/><circle cx="12" cy="13" r="0.5" fill="url(#bronze-grad)"/></svg>'
        ];
    }

    /**
     * Get recent Match History (Evaluations) for an agent
     */
    public function getAgentMatchHistory(array $filters = [], int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        $query = Evaluation::query()
            ->with(['campaign.parent', 'evaluator:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit);

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * Paginate Agent Match History (Evaluations)
     */
    public function paginateAgentMatchHistory(array $filters = [], int $perPage = 5): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Evaluation::query()
            ->with(['campaign.parent', 'evaluator:id,name'])
            ->orderByDesc('created_at');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }
}
