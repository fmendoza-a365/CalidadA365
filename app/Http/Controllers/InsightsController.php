<?php

namespace App\Http\Controllers;

use App\Models\InsightReport;
use App\Models\Evaluation;
use App\Models\Campaign;
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
        $reports = InsightReport::with('campaign', 'creator')->latest()->paginate(10);
        $campaigns = Campaign::where('is_active', true)->get();

        // Comprehensive Stats
        $user = auth()->user();
        $totalEvaluations = Evaluation::forUser($user)->count();
        $avgScore = Evaluation::forUser($user)->avg('percentage_score') ?? 0;
        $complianceRate = $totalEvaluations > 0 
            ? (Evaluation::forUser($user)->where('percentage_score', '>=', 80)->count() / $totalEvaluations) * 100 
            : 0;

        // Top Failed Criteria
        $topFailedCriteria = \App\Models\EvaluationItem::whereHas('evaluation', function($query) use ($user) {
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
        $criticalFailures = \App\Models\EvaluationItem::whereHas('evaluation', function($query) use ($user) {
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
        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'type' => 'required|in:operational,strategic',
            'days' => 'required|integer|min:1|max:90',
        ]);

        $startDate = Carbon::now()->subDays($validated['days']);
        $endDate = Carbon::now();

        // Fetch evaluations with comprehensive eager loading to prevent N+1 queries
        $evaluations = Evaluation::where('campaign_id', $validated['campaign_id'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with([
                'items.subAttribute:id,name,attribute_id,is_critical', // Eager load sub-attributes
                'items.subAttribute.attribute:id,name', // Eager load parent attributes
                'campaign:id,name,description', // Only needed columns
                'formVersion.formAttributes.subAttributes', // Form criteria for analysis
                'agent:id,name' // Agent info for analysis
            ])
            ->get();

        if ($evaluations->isEmpty()) {
            return back()->with('error', 'No se encontraron evaluaciones en el periodo seleccionado.');
        }

        // Generate Report
        $aiResult = $this->aiService->generateInsightReport($evaluations, $validated['type']);

        InsightReport::create([
            'campaign_id' => $validated['campaign_id'],
            'type' => $validated['type'],
            'date_range_start' => $startDate,
            'date_range_end' => $endDate,
            'summary_content' => $aiResult['executive_summary'] ?? $aiResult['summary'] ?? 'Sin resumen',
            'key_findings' => $aiResult, // Store entire result as JSON
            'generated_by' => auth()->id(),
        ]);

        return redirect()->route('insights.index')->with('success', 'Reporte de Insights generado exitosamente.');
    }

    public function show(InsightReport $insight)
    {
        return view('insights.show', compact('insight'));
    }

    public function destroy(InsightReport $insight)
    {
        $insight->delete();
        
        return redirect()->route('insights.index')->with('success', 'Reporte eliminado exitosamente.');
    }
}
