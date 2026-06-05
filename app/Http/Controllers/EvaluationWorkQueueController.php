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

        $pendingReviewQuery = Evaluation::with(['agent', 'campaign.parent', 'interaction', 'reviewClaimer'])
            ->forUser($user)
            ->availableForReviewBy($user)
            ->whereIn('status', [
                Evaluation::STATUS_PENDING_MONITOR_REVIEW,
                Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
            ])
            ->whereDoesntHave('interaction.manualEvaluation');

        $aiQueueQuery = Evaluation::with(['agent', 'campaign.parent', 'interaction'])
            ->forUser($user)
            ->whereIn('status', [
                Evaluation::STATUS_PENDING_AI,
                Evaluation::STATUS_AI_PROCESSING,
                Evaluation::STATUS_AI_FAILED,
            ])
            ->whereDoesntHave('interaction.manualEvaluation');

        $disputesQuery = DisputeResolution::with([
            'evaluation.agent',
            'evaluation.campaign.parent',
            'evaluation.interaction',
        ])
            ->whereHas('evaluation', function ($query) use ($user) {
                $query->forUser($user)->where('status', '!=', Evaluation::STATUS_CLOSED);
            })
            ->where('status', '!=', DisputeResolution::STATUS_RESOLVED);

        $filters = [
            'start_date' => $request->input('start_date', now()->subDays(30)->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
            'campaign_id' => $request->input('campaign_id'),
        ];

        $calibrationAlerts = collect($calibrationService->recentPairs($filters, $user, 50))
            ->filter(fn (array $pair) => $pair['absolute_score_delta'] >= 10)
            ->values();
        $productivity = $operationalMetrics->summary($filters, $user);
        if ($request->filled('campaign_id')) {
            $campaignIds = Campaign::idsForFilter($request->input('campaign_id'));

            $pendingReviewQuery->whereIn('campaign_id', $campaignIds);
            $aiQueueQuery->whereIn('campaign_id', $campaignIds);
            $disputesQuery->whereHas('evaluation', fn ($query) => $query->whereIn('campaign_id', $campaignIds));
        }

        $campaigns = Campaign::active()->forUser($user)->orderedForSelect()->get();

        $counts = [
            'pending_review' => (clone $pendingReviewQuery)->count(),
            'ai_queue' => (clone $aiQueueQuery)->count(),
            'disputes' => (clone $disputesQuery)->count(),
            'calibration_alerts' => $calibrationAlerts->count(),
        ];

        $pendingReview = $pendingReviewQuery
            ->latest()
            ->paginate(8, ['*'], 'review_page')
            ->withQueryString();
        $aiQueue = $aiQueueQuery
            ->latest()
            ->paginate(8, ['*'], 'ai_page')
            ->withQueryString();
        $disputes = $disputesQuery
            ->latest()
            ->paginate(6, ['*'], 'dispute_page')
            ->withQueryString();
        $calibrationAlerts = $calibrationAlerts->take(6);

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
