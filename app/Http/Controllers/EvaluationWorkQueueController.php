<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\DisputeResolution;
use App\Models\Evaluation;
use App\Services\EvaluationCalibrationService;
use App\Services\OperationalMetricsService;
use Illuminate\Http\Request;

class EvaluationWorkQueueController extends Controller
{
    public function index(Request $request, EvaluationCalibrationService $calibrationService, OperationalMetricsService $operationalMetrics)
    {
        $user = auth()->user();

        if (! $user->can('view_work_queue') && ! $user->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator', 'qa_monitor', 'supervisor', 'manager'])) {
            abort(403);
        }

        $pendingReview = Evaluation::with(['agent', 'campaign', 'interaction'])
            ->forUser($user)
            ->whereIn('status', [
                Evaluation::STATUS_PENDING_MONITOR_REVIEW,
                Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
            ])
            ->latest()
            ->limit(25)
            ->get();

        $aiQueue = Evaluation::with(['agent', 'campaign', 'interaction'])
            ->forUser($user)
            ->whereIn('status', [
                Evaluation::STATUS_PENDING_AI,
                Evaluation::STATUS_AI_PROCESSING,
                Evaluation::STATUS_AI_FAILED,
            ])
            ->latest()
            ->limit(25)
            ->get();

        $disputes = DisputeResolution::with([
            'evaluation.agent',
            'evaluation.campaign',
            'evaluation.interaction',
        ])
            ->whereHas('evaluation', function ($query) use ($user) {
                $query->forUser($user)->where('status', '!=', Evaluation::STATUS_CLOSED);
            })
            ->where('status', '!=', DisputeResolution::STATUS_RESOLVED)
            ->latest()
            ->limit(25)
            ->get();

        $filters = [
            'start_date' => $request->input('start_date', now()->subDays(30)->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
            'campaign_id' => $request->input('campaign_id'),
        ];

        $calibrationAlerts = collect($calibrationService->recentPairs($filters, $user, 50))
            ->filter(fn (array $pair) => $pair['absolute_score_delta'] >= 10)
            ->take(25)
            ->values();
        $productivity = $operationalMetrics->summary($filters, $user);
        $campaigns = Campaign::active()->forUser($user)->orderBy('name')->get();

        $counts = [
            'pending_review' => $pendingReview->count(),
            'ai_queue' => $aiQueue->count(),
            'disputes' => $disputes->count(),
            'calibration_alerts' => $calibrationAlerts->count(),
        ];

        return view('work-queue.index', compact(
            'pendingReview',
            'aiQueue',
            'disputes',
            'calibrationAlerts',
            'productivity',
            'counts',
            'filters',
            'campaigns'
        ));
    }
}
