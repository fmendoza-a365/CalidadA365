<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\InsightReport;
use App\Services\AIEvaluationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InsightsController extends Controller
{
    protected $aiService;

    public function __construct(AIEvaluationService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function index(Request $request)
    {
        // Comprehensive Stats
        $user = auth()->user();
        $reports = InsightReport::with('campaign', 'creator')
            ->where(function ($query) use ($user) {
                $query
                    ->where('generated_by', $user->id)
                    ->orWhereHas('campaign', function ($campaignQuery) use ($user) {
                        $campaignQuery->forUser($user);
                    });

                if ($user->hasAnyRole(['admin', 'qa_manager'])) {
                    $query->orWhereNull('campaign_id');
                }
            })
            ->latest()
            ->paginate(10);
        $campaigns = Campaign::forUser($user)->active()->orderBy('name')->get();

        $totalEvaluations = Evaluation::forUser($user)->count();
        $avgScore = Evaluation::forUser($user)->avg('percentage_score') ?? 0;
        $complianceRate = $totalEvaluations > 0
            ? (Evaluation::forUser($user)->where('percentage_score', '>=', 80)->count() / $totalEvaluations) * 100
            : 0;

        // Top Failed Criteria
        $topFailedCriteria = \App\Models\EvaluationItem::whereHas('evaluation', function ($query) use ($user) {
            $query->forUser($user);
        })
            ->where('status', 'non_compliant')
            ->join('quality_subattributes', 'evaluation_items.subattribute_id', '=', 'quality_subattributes.id')
            ->selectRaw('quality_subattributes.name, count(*) as count')
            ->groupBy('quality_subattributes.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // Critical Failures (Critical items that failed)
        $criticalFailures = \App\Models\EvaluationItem::whereHas('evaluation', function ($query) use ($user) {
            $query->forUser($user);
        })
            ->where('status', 'non_compliant')
            ->join('quality_subattributes', 'evaluation_items.subattribute_id', '=', 'quality_subattributes.id')
            ->where('quality_subattributes.is_critical', true)
            ->count();

        // Score Trend (last 30 days vs previous 30 days)
        $currentPeriodAvg = Evaluation::forUser($user)->where('created_at', '>=', now()->subDays(30))->avg('percentage_score') ?? 0;
        $previousPeriodAvg = Evaluation::forUser($user)->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->avg('percentage_score') ?? 0;
        $trendDirection = $currentPeriodAvg > $previousPeriodAvg ? 'up' : ($currentPeriodAvg < $previousPeriodAvg ? 'down' : 'stable');
        $trendChange = $previousPeriodAvg > 0 ? (($currentPeriodAvg - $previousPeriodAvg) / $previousPeriodAvg) * 100 : 0;

        // Top/Bottom Performers (last 30 days, min 3 evals)
        $topPerformers = Evaluation::forUser($user)
            ->select('agent_id', \DB::raw('AVG(percentage_score) as avg_score'), \DB::raw('COUNT(*) as eval_count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('agent_id')
            ->havingRaw('COUNT(*) >= ?', [3])
            ->orderByDesc('avg_score')
            ->limit(3)
            ->with('agent:id,name')
            ->get();

        $bottomPerformers = Evaluation::forUser($user)
            ->select('agent_id', \DB::raw('AVG(percentage_score) as avg_score'), \DB::raw('COUNT(*) as eval_count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('agent_id')
            ->havingRaw('COUNT(*) >= ?', [3])
            ->orderBy('avg_score')
            ->limit(3)
            ->with('agent:id,name')
            ->get();

        $stats = [
            'total_evaluations' => $totalEvaluations,
            'avg_score' => $avgScore,
            'compliance_rate' => $complianceRate,
            'top_failed_criteria' => $topFailedCriteria,
            'critical_failures' => $criticalFailures,
            'trend_direction' => $trendDirection,
            'trend_change' => abs($trendChange),
            'top_performers' => $topPerformers,
            'bottom_performers' => $bottomPerformers,
        ];

        return view('insights.index', compact('reports', 'campaigns', 'stats'));
    }

    public function generate(Request $request)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'campaign_id' => 'nullable|exists:campaigns,id',
            'type' => 'required|in:operational,strategic',
            'days' => 'required|integer|min:1|max:90',
        ]);

        $campaign = null;
        if (! empty($validated['campaign_id'])) {
            $campaign = Campaign::forUser($user)->whereKey($validated['campaign_id'])->first();
        }

        if (! empty($validated['campaign_id']) && ! $campaign) {
            abort(403, 'No tiene permiso para generar insights en esta campaña.');
        }

        $startDate = Carbon::now()->subDays($validated['days']);
        $endDate = Carbon::now();

        // Fetch evaluations with comprehensive eager loading to prevent N+1 queries
        $evaluations = Evaluation::forUser($user)
            ->when($campaign, fn ($query) => $query->where('campaign_id', $campaign->id))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with([
                'items.subAttribute:id,name,attribute_id,is_critical',
                'items.subAttribute.attribute:id,name',
                'campaign:id,name,description',
                'formVersion.formAttributes.subAttributes',
                'agent:id,name',
            ])
            ->get();

        if ($evaluations->isEmpty()) {
            return back()->with('error', 'No se encontraron evaluaciones en el periodo seleccionado.');
        }

        $reportSnapshot = $this->buildReportSnapshot($evaluations, $campaign, $startDate, $endDate, $validated['type']);
        $aiResult = $this->aiService->generateInsightReport($evaluations, $validated['type'], $reportSnapshot);
        $aiResult['report_snapshot'] = $reportSnapshot;

        $report = InsightReport::create([
            'campaign_id' => $campaign?->id,
            'type' => $validated['type'],
            'date_range_start' => $startDate,
            'date_range_end' => $endDate,
            'summary_content' => $aiResult['executive_summary'] ?? $aiResult['summary'] ?? 'Sin resumen',
            'key_findings' => $aiResult,
            'generated_by' => auth()->id(),
        ]);

        return redirect()->route('insights.show', $report)->with('success', 'Reporte de Insights generado exitosamente.');
    }

    public function show(InsightReport $insight)
    {
        $this->ensureReportAccess($insight);

        $findings = $insight->key_findings ?? [];
        $snapshot = $findings['report_snapshot'] ?? [];

        return view('insights.show', compact('insight', 'findings', 'snapshot'));
    }

    public function destroy(InsightReport $insight)
    {
        $this->ensureReportAccess($insight);

        $insight->delete();

        return redirect()->route('insights.index')->with('success', 'Reporte eliminado exitosamente.');
    }

    private function ensureReportAccess(InsightReport $insight): void
    {
        $user = auth()->user();

        if (! $insight->campaign_id) {
            if ($user->hasAnyRole(['admin', 'qa_manager']) || $insight->generated_by === $user->id) {
                return;
            }

            abort(403, 'No tiene permiso para ver este reporte.');
        }

        if (! Campaign::forUser($user)->whereKey($insight->campaign_id)->exists()) {
            abort(403, 'No tiene permiso para ver este reporte.');
        }
    }

    private function buildReportSnapshot($evaluations, ?Campaign $campaign, Carbon $startDate, Carbon $endDate, string $type): array
    {
        $scoredEvaluations = $evaluations->filter(fn (Evaluation $evaluation) => $evaluation->percentage_score !== null);
        $items = $evaluations->flatMap(fn (Evaluation $evaluation) => $evaluation->items);
        $failedItems = $items->filter(fn (EvaluationItem $item) => $item->status === 'non_compliant');
        $criticalFailures = $failedItems->filter(fn (EvaluationItem $item) => (bool) ($item->subAttribute?->is_critical))->count();
        $averageScore = $scoredEvaluations->avg(fn (Evaluation $evaluation) => (float) $evaluation->percentage_score);
        $complianceCount = $scoredEvaluations->filter(fn (Evaluation $evaluation) => (float) $evaluation->percentage_score >= 80)->count();

        return [
            'scope' => [
                'campaign_id' => $campaign?->id,
                'campaign_name' => $campaign?->name ?? 'Todas las campañas visibles',
                'type' => $type,
                'date_range_start' => $startDate->toDateString(),
                'date_range_end' => $endDate->toDateString(),
            ],
            'metrics' => [
                'total_evaluations' => $evaluations->count(),
                'scored_evaluations' => $scoredEvaluations->count(),
                'average_score' => round((float) ($averageScore ?? 0), 1),
                'compliance_rate' => $scoredEvaluations->isNotEmpty() ? round(($complianceCount / $scoredEvaluations->count()) * 100, 1) : 0,
                'critical_failures' => $criticalFailures,
                'non_compliant_items' => $failedItems->count(),
                'manual_evaluations' => $evaluations->where('type', 'manual')->count(),
                'ai_evaluations' => $evaluations->where('type', 'ai')->count(),
            ],
            'score_distribution' => $this->scoreDistribution($scoredEvaluations),
            'top_failed_criteria' => $this->topFailedCriteria($failedItems),
            'agent_performance' => $this->agentPerformance($evaluations),
            'campaign_breakdown' => $this->campaignBreakdown($evaluations),
            'score_trend' => $this->scoreTrend($scoredEvaluations),
        ];
    }

    private function scoreDistribution($evaluations): array
    {
        $bands = [
            'excellent' => ['label' => '90%+', 'count' => 0],
            'good' => ['label' => '80%-89%', 'count' => 0],
            'watch' => ['label' => '70%-79%', 'count' => 0],
            'critical' => ['label' => '<70%', 'count' => 0],
        ];

        foreach ($evaluations as $evaluation) {
            $score = (float) $evaluation->percentage_score;
            if ($score >= 90) {
                $bands['excellent']['count']++;
            } elseif ($score >= 80) {
                $bands['good']['count']++;
            } elseif ($score >= 70) {
                $bands['watch']['count']++;
            } else {
                $bands['critical']['count']++;
            }
        }

        return array_values($bands);
    }

    private function topFailedCriteria($failedItems): array
    {
        return $failedItems
            ->groupBy(fn (EvaluationItem $item) => $item->subAttribute?->name ?? 'Criterio no identificado')
            ->map(function ($items, string $criteria) {
                $first = $items->first();

                return [
                    'criteria' => $criteria,
                    'category' => $first->subAttribute?->attribute?->name ?? 'Sin categoría',
                    'count' => $items->count(),
                    'critical' => (bool) ($first->subAttribute?->is_critical),
                    'examples' => $items
                        ->pluck('evidence_quote')
                        ->filter()
                        ->take(2)
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc('count')
            ->take(8)
            ->values()
            ->all();
    }

    private function agentPerformance($evaluations): array
    {
        return $evaluations
            ->groupBy(fn (Evaluation $evaluation) => $evaluation->agent?->id ?? 'sin-agente')
            ->map(function ($agentEvaluations) {
                $first = $agentEvaluations->first();
                $scored = $agentEvaluations->filter(fn (Evaluation $evaluation) => $evaluation->percentage_score !== null);

                return [
                    'agent' => $first->agent?->name ?? 'Sin asesor',
                    'evaluations' => $agentEvaluations->count(),
                    'average_score' => round((float) ($scored->avg(fn (Evaluation $evaluation) => (float) $evaluation->percentage_score) ?? 0), 1),
                    'critical_failures' => $agentEvaluations
                        ->flatMap(fn (Evaluation $evaluation) => $evaluation->items)
                        ->filter(fn (EvaluationItem $item) => $item->status === 'non_compliant' && (bool) ($item->subAttribute?->is_critical))
                        ->count(),
                ];
            })
            ->sortBy('average_score')
            ->take(6)
            ->values()
            ->all();
    }

    private function campaignBreakdown($evaluations): array
    {
        return $evaluations
            ->groupBy(fn (Evaluation $evaluation) => $evaluation->campaign?->name ?? 'Sin campaña')
            ->map(function ($campaignEvaluations, string $campaignName) {
                $scored = $campaignEvaluations->filter(fn (Evaluation $evaluation) => $evaluation->percentage_score !== null);

                return [
                    'campaign' => $campaignName,
                    'evaluations' => $campaignEvaluations->count(),
                    'average_score' => round((float) ($scored->avg(fn (Evaluation $evaluation) => (float) $evaluation->percentage_score) ?? 0), 1),
                ];
            })
            ->sortByDesc('evaluations')
            ->values()
            ->all();
    }

    private function scoreTrend($evaluations): array
    {
        return $evaluations
            ->groupBy(fn (Evaluation $evaluation) => $evaluation->created_at->toDateString())
            ->map(fn ($dayEvaluations, string $date) => [
                'date' => $date,
                'evaluations' => $dayEvaluations->count(),
                'average_score' => round((float) ($dayEvaluations->avg(fn (Evaluation $evaluation) => (float) $evaluation->percentage_score) ?? 0), 1),
            ])
            ->sortBy('date')
            ->values()
            ->all();
    }
}
