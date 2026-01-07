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

        $query = Evaluation::query();
        
        // Apply role-based filtering
        $query->forUser(auth()->user());

        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }

        $value = match ($metric) {
            'total_evaluations' => $query->count(),
            'avg_score' => round($query->avg('percentage_score') ?? 0, 2),
            'pending_disputes' => DisputeResolution::whereNull('resolved_at')->count(),
            'active_campaigns' => Campaign::active()->count(),
            
            // NEW METRICS
            'total_agents' => \App\Models\User::role('agent')->count(),
            
            'evaluations_this_month' => Evaluation::query()
                ->forUser(auth()->user())
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
            
            'compliance_rate' => (function() use ($query) {
                $total = (clone $query)->count();
                if ($total === 0) return 0;
                $compliant = (clone $query)->where('percentage_score', '>=', 80)->count();
                return round(($compliant / $total) * 100, 2);
            })(),
            
            'response_rate' => (function() {
                $visible = Evaluation::where('status', 'visible_to_agent')->count();
                if ($visible === 0) return 100;
                $responded = Evaluation::where('status', 'agent_responded')->count();
                return round(($responded / ($visible + $responded)) * 100, 2);
            })(),
            
            'avg_resolution_time' => (function() {
                $avgSeconds = DisputeResolution::whereNotNull('resolved_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, resolved_at)) as avg_time')
                    ->value('avg_time');
                return $avgSeconds ? round($avgSeconds / 86400, 1) : 0; // Convert to days
            })(),
            
            'top_performer' => (function() {
                $topAgent = Evaluation::select('agent_id', \DB::raw('AVG(percentage_score) as avg_score'))
                    ->where('created_at', '>=', now()->subDays(30))
                    ->groupBy('agent_id')
                    ->havingRaw('COUNT(*) >= 3')
                    ->orderByDesc('avg_score')
                    ->with('agent:id,name')
                    ->first();
                return $topAgent ? $topAgent->agent->name : 'N/A';
            })(),
            
            'worst_performer' => (function() {
                $worstAgent = Evaluation::select('agent_id', \DB::raw('AVG(percentage_score) as avg_score'))
                    ->where('created_at', '>=', now()->subDays(30))
                    ->groupBy('agent_id')
                    ->havingRaw('COUNT(*) >= 3')
                    ->orderBy('avg_score')
                    ->with('agent:id,name')
                    ->first();
                return $worstAgent ? $worstAgent->agent->name : 'N/A';
            })(),
            
            default => 0,
        };

        return [
            'value' => $value,
            'metric' => $metric,
        ];
    }

    private function getLineChartData($config)
    {
        $metric = $config['metric'] ?? 'avg_score';
        $campaignId = $config['campaign_id'] ?? null;
        $days = $config['days'] ?? 30;

        $query = Evaluation::query()
            ->forUser(auth()->user())
            ->where('created_at', '>=', now()->subDays($days));

        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }

        $data = $query
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('AVG(percentage_score) as avg_score'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $data->pluck('date')->toArray(),
            'datasets' => [
                [
                    'label' => $metric === 'avg_score' ? 'Promedio de Calidad' : 'Cantidad',
                    'data' => $data->pluck($metric === 'avg_score' ? 'avg_score' : 'count')->toArray(),
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
                    $query->where('created_at', '>=', now()->subDays(30));
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

        if ($type === 'recent_evaluations') {
            $evaluations = Evaluation::with(['agent', 'campaign'])
                ->forUser(auth()->user())
                ->latest()
                ->limit($limit)
                ->get();

            return [
                'columns' => ['Fecha', 'Campaña', 'Agente', 'Puntaje', 'Estado'],
                'rows' => $evaluations->map(function ($eval) {
                    return [
                        $eval->created_at->format('d/m/Y H:i'),
                        $eval->campaign->name,
                        $eval->agent->name,
                        number_format($eval->percentage_score, 0) . '%',
                        $eval->status,
                    ];
                })->toArray()
            ];
        }

        if ($type === 'top_agents') {
            $topAgents = Evaluation::select('agent_id', 
                    \DB::raw('AVG(percentage_score) as avg_score'),
                    \DB::raw('COUNT(*) as eval_count'))
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('agent_id')
                ->havingRaw('COUNT(*) >= 3')
                ->orderByDesc('avg_score')
                ->limit($limit)
                ->with('agent:id,name')
                ->get();

            return [
                'columns' => ['Agente', 'Promedio', 'Evaluaciones'],
                'rows' => $topAgents->map(function ($item) {
                    return [
                        $item->agent->name,
                        number_format($item->avg_score, 1) . '%',
                        $item->eval_count,
                    ];
                })->toArray()
            ];
        }

        if ($type === 'bottom_agents') {
            $bottomAgents = Evaluation::select('agent_id', 
                    \DB::raw('AVG(percentage_score) as avg_score'),
                    \DB::raw('COUNT(*) as eval_count'))
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('agent_id')
                ->havingRaw('COUNT(*) >= 3')
                ->orderBy('avg_score')
                ->limit($limit)
                ->with('agent:id,name')
                ->get();

            return [
                'columns' => ['Agente', 'Promedio', 'Evaluaciones'],
                'rows' => $bottomAgents->map(function ($item) {
                    return [
                        $item->agent->name,
                        number_format($item->avg_score, 1) . '%',
                        $item->eval_count,
                    ];
                })->toArray()
            ];
        }

        if ($type === 'disputed_items') {
            $disputes = \App\Models\AgentResponse::with(['evaluation.agent', 'evaluation.campaign'])
                ->where('status', 'disputed')
                ->latest()
                ->limit($limit)
                ->get();

            return [
                'columns' => ['Fecha', 'Agente', 'Campaña', 'Motivo'],
                'rows' => $disputes->map(function ($dispute) {
                    return [
                        $dispute->created_at->format('d/m/Y'),
                        $dispute->evaluation->agent->name ?? 'N/A',
                        $dispute->evaluation->campaign->name ?? 'N/A',
                        \Str::limit($dispute->comments ?? 'Sin motivo', 30),
                    ];
                })->toArray()
            ];
        }

        return ['columns' => [], 'rows' => []];
    }
}
