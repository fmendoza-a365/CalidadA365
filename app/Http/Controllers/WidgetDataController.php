<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\Campaign;
use App\Models\DisputeResolution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WidgetDataController extends Controller
{
    /**
     * Get data for a specific widget type based on config
     */
    public function getData(Request $request)
    {
        $validated = $request->validate([
            'widget_type' => 'required|string',
            'config' => 'nullable|array',
        ]);

        $widgetType = $validated['widget_type'];
        $config = $validated['config'] ?? [];

        $data = match ($widgetType) {
            'stats_card' => $this->getStatsCardData($config),
            'line_chart' => $this->getLineChartData($config),
            'bar_chart' => $this->getBarChartData($config),
            'pie_chart' => $this->getPieChartData($config),
            'table' => $this->getTableData($config),
            default => ['error' => 'Invalid widget type'],
        };

        return response()->json($data);
    }

    private function getStatsCardData($config)
    {
        $metric = $config['metric'] ?? 'total_evaluations';
        $campaignId = $config['campaign_id'] ?? null;
        $showComparison = $config['show_comparison'] ?? false;

        $query = Evaluation::query();
        $query->forUser(auth()->user());

        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }

        // Helper to get value for a specific period
        $getValue = function($periodQuery) use ($metric) {
            return match ($metric) {
                'total_evaluations' => $periodQuery->count(),
                'avg_score' => round($periodQuery->avg('percentage_score') ?? 0, 2),
                'pending_disputes' => DisputeResolution::whereNull('resolved_at')->count(), // Not period sensitive strictly speaking
                'active_campaigns' => Campaign::active()->count(),
                'total_agents' => \App\Models\User::role('agent')->count(),
                'evaluations_this_month' => (clone $periodQuery)->count(), // Query already filtered by month
                'compliance_rate' => (function() use ($periodQuery) {
                    $total = (clone $periodQuery)->count();
                    if ($total === 0) return 0;
                    $compliant = (clone $periodQuery)->where('percentage_score', '>=', 80)->count();
                    return round(($compliant / $total) * 100, 2);
                })(),
                'response_rate' => (function() { return 0; })(), // Complex to calculate historically
                'avg_resolution_time' => 0, // Complex
                default => 0,
            };
        };

        // Current Value
        $currentQuery = clone $query;
        if ($metric === 'evaluations_this_month') {
             $currentQuery->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month);
        }
        $value = $getValue($currentQuery);

        $comparison = null;
        if ($showComparison) {
            // Previous Period Logic (simplistic: previous month)
            $previousQuery = clone $query;
            if ($metric === 'evaluations_this_month') {
                $previousQuery->whereYear('created_at', now()->subMonth()->year)->whereMonth('created_at', now()->subMonth()->month);
            } else {
                 // For general metrics, assume "current" is all time, so comparison is hard.
                 // Let's assume for standard metrics we compare "Last 30 days" vs "30-60 days ago" implicitly if they are time-bound
                 // For now, only implement comparison for explicitly time-bound metrics or assume monthly change
                 $previousQuery->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)]);
                 $currentForComp = (clone $query)->where('created_at', '>=', now()->subDays(30)); 
                 // Re-calculate value for "This Month" if generic metric
                 $value = $getValue($currentForComp); 
            }
            
            $prevValue = $getValue($previousQuery);
            
            if ($prevValue > 0) {
                $change = (($value - $prevValue) / $prevValue) * 100;
                $comparison = [
                    'value' => $prevValue,
                    'change' => round($change, 1),
                    'direction' => $change >= 0 ? 'up' : 'down'
                ];
            } else {
                 $comparison = ['value' => 0, 'change' => 100, 'direction' => 'up'];
            }
        }

        return [
            'value' => $value,
            'metric' => $metric,
            'comparison' => $comparison
        ];
    }

    private function getLineChartData($config)
    {
        $metric = $config['metric'] ?? 'avg_score';
        $campaignId = $config['campaign_id'] ?? null;
        $days = $config['days'] ?? 30;
        $groupBy = $config['group_by'] ?? 'day'; // day, month, year, hour

        $query = Evaluation::query()
            ->forUser(auth()->user())
            ->where('created_at', '>=', now()->subDays($days));

        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }

        // Postgres Date Truncation
        $dateFormat = match($groupBy) {
            'hour' => "TO_CHAR(created_at, 'YYYY-MM-DD HH24:00')",
            'month' => "TO_CHAR(created_at, 'YYYY-MM')",
            'year' => "TO_CHAR(created_at, 'YYYY')",
            'week' => "TO_CHAR(created_at, 'YYYY-IW')",
            default => "TO_CHAR(created_at, 'YYYY-MM-DD')", // day
        };

        // SQLite Fallback (for local dev if needed, though we prioritize PgSQL)
        if (DB::getDriverName() === 'sqlite') {
             $dateFormat = match($groupBy) {
                'hour' => "strftime('%Y-%m-%d %H:00', created_at)",
                'month' => "strftime('%Y-%m', created_at)",
                'year' => "strftime('%Y', created_at)",
                'week' => "strftime('%Y-%W', created_at)",
                default => "strftime('%Y-%m-%d', created_at)",
            };
        }

        $data = $query
            ->select(
                DB::raw("$dateFormat as date_group"),
                DB::raw('AVG(percentage_score) as avg_score'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date_group')
            ->orderBy('date_group')
            ->get();

        return [
            'labels' => $data->pluck('date_group')->toArray(),
            'datasets' => [
                [
                    'label' => $metric === 'avg_score' ? 'Promedio de Calidad' : 'Cantidad',
                    'data' => $data->pluck($metric === 'avg_score' ? 'avg_score' : 'count')->toArray(),
                    'fill' => true,
                    'tension' => 0.4
                ]
            ]
        ];
    }

    private function getBarChartData($config)
    {
        $metric = $config['metric'] ?? 'campaign_performance';

        if ($metric === 'campaign_performance') {
            $campaigns = Campaign::withCount(['evaluations' => function ($query) {
                $query->forUser(auth()->user());
            }])
            ->with(['evaluations' => function ($query) {
                $query->forUser(auth()->user());
            }])
            ->get();

            return [
                'labels' => $campaigns->pluck('name')->toArray(),
                'datasets' => [
                    [
                        'label' => 'Evaluaciones',
                        'data' => $campaigns->pluck('evaluations_count')->toArray(),
                        'backgroundColor' => '#4F46E5',
                    ]
                ]
            ];
        }

        if ($metric === 'agent_performance') {
            $agents = \App\Models\User::role('agent')
                ->with(['evaluations' => function ($query) {
                    $query->forUser(auth()->user())
                          ->where('created_at', '>=', now()->subDays(30));
                }])
                ->get()
                ->map(function ($agent) {
                    return [
                        'name' => $agent->name,
                        'avg_score' => $agent->evaluations->avg('percentage_score') ?? 0
                    ];
                })
                ->filter(fn($agent) => $agent['avg_score'] > 0)
                ->sortByDesc('avg_score')
                ->take(10);

            return [
                'labels' => $agents->pluck('name')->toArray(),
                'datasets' => [
                    [
                        'label' => 'Promedio de Calidad',
                        'data' => $agents->pluck('avg_score')->toArray(),
                        'backgroundColor' => '#10B981',
                    ]
                ]
            ];
        }

        return ['labels' => [], 'datasets' => []];
    }

    private function getPieChartData($config)
    {
        $metric = $config['metric'] ?? 'status_distribution';

        if ($metric === 'status_distribution') {
            $statuses = Evaluation::forUser(auth()->user())
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get();

            $statusLabels = [
                'visible_to_agent' => 'Pendiente Firma',
                'agent_responded' => 'Firmada',
                'disputed' => 'En Disputa',
                'resolved' => 'Resuelta',
            ];

            return [
                'labels' => $statuses->map(fn($s) => $statusLabels[$s->status] ?? $s->status)->toArray(),
                'datasets' => [
                    [
                        'data' => $statuses->pluck('count')->toArray(),
                        'backgroundColor' => ['#10B981', '#F59E0B', '#EF4444', '#6366F1'],
                    ]
                ]
            ];
        }

        return ['labels' => [], 'datasets' => []];
    }

    private function getTableData($config)
    {
        $type = $config['type'] ?? 'recent_evaluations';
        $limit = $config['limit'] ?? 10;
        $visibleColumns = $config['visible_columns'] ?? [];

        // Helper to filter columns and rows
        $filterData = function($columns, $rows) use ($visibleColumns) {
            if (empty($visibleColumns)) {
                return ['columns' => $columns, 'rows' => $rows];
            }

            // Find indices of visible columns
            $visibleIndices = [];
            $finalColumns = [];
            foreach ($columns as $index => $col) {
                if (in_array($col, $visibleColumns)) {
                    $visibleIndices[] = $index;
                    $finalColumns[] = $col;
                }
            }

            // If no valid columns selected, return all (fallback)
            if (empty($finalColumns)) {
                return ['columns' => $columns, 'rows' => $rows];
            }

            // Filter rows based on indices
            $finalRows = array_map(function($row) use ($visibleIndices) {
                $filteredRow = [];
                foreach ($visibleIndices as $index) {
                    $filteredRow[] = $row[$index] ?? '';
                }
                return $filteredRow;
            }, $rows);

            return ['columns' => $finalColumns, 'rows' => $finalRows];
        };

        if ($type === 'recent_evaluations') {
            $evaluations = Evaluation::with(['agent', 'campaign'])
                ->forUser(auth()->user())
                ->latest()
                ->limit($limit)
                ->get();

            $allColumns = ['ID', 'Fecha', 'Campaña', 'Agente', 'Puntaje', 'Estado'];
            $allRows = $evaluations->map(function ($eval) {
                return [
                    $eval->id,
                    $eval->created_at->format('d/m/Y H:i'),
                    $eval->campaign->name,
                    $eval->agent->name,
                    number_format($eval->percentage_score, 0) . '%',
                    $eval->status,
                ];
            })->toArray();

            return $filterData($allColumns, $allRows);
        }

        if ($type === 'top_agents') {
            $topAgents = Evaluation::forUser(auth()->user())
                ->select('agent_id', 
                    \DB::raw('AVG(percentage_score) as avg_score'),
                    \DB::raw('COUNT(*) as eval_count'))
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('agent_id')
                ->havingRaw('COUNT(*) >= 3')
                ->orderByDesc('avg_score')
                ->limit($limit)
                ->with('agent:id,name')
                ->get();

            $allColumns = ['Agente', 'Promedio', 'Evaluaciones'];
            $allRows = $topAgents->map(function ($agent) {
                return [
                    $agent->agent->name,
                    number_format($agent->avg_score, 1) . '%',
                    $agent->eval_count
                ];
            })->toArray();

            return $filterData($allColumns, $allRows);
        }

        if ($type === 'bottom_agents') {
            $bottomAgents = Evaluation::forUser(auth()->user())
                ->select('agent_id', 
                    \DB::raw('AVG(percentage_score) as avg_score'),
                    \DB::raw('COUNT(*) as eval_count'))
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('agent_id')
                ->havingRaw('COUNT(*) >= 3')
                ->orderBy('avg_score')
                ->limit($limit)
                ->with('agent:id,name')
                ->get();

             $allColumns = ['Agente', 'Promedio', 'Evaluaciones'];
             $allRows = $bottomAgents->map(function ($agent) {
                return [
                    $agent->agent->name,
                    number_format($agent->avg_score, 1) . '%',
                    $agent->eval_count
                ];
            })->toArray();

            return $filterData($allColumns, $allRows);
        }

        if ($type === 'disputed_items') {
             $disputes = \App\Models\DisputeResolution::with(['evaluation' => function($query) {
                    $query->forUser(auth()->user());
                }, 'evaluation.agent'])
                ->whereHas('evaluation', function($query) {
                    $query->forUser(auth()->user());
                })
                ->whereNull('resolved_at')
                ->latest()
                ->limit($limit)
                ->get();

             $allColumns = ['Fecha', 'Agente', 'Motivo', 'Estado'];
             $allRows = $disputes->map(function ($dispute) {
                return [
                    $dispute->created_at->format('d/m/Y'),
                    $dispute->evaluation->agent->name ?? 'N/A',
                    \Str::limit($dispute->comments ?? 'Sin motivo', 30),
                    'Pendiente'
                ];
            })->toArray();

            return $filterData($allColumns, $allRows);
        }

        return ['columns' => [], 'rows' => []];
    }
}
