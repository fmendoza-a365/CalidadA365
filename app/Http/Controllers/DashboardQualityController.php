<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\EvaluationCalibrationService;
use App\Services\QualityAnalyticsService;
use Illuminate\Http\Request;

class DashboardQualityController extends Controller
{
    protected QualityAnalyticsService $analytics;

    protected EvaluationCalibrationService $calibration;

    public function __construct(QualityAnalyticsService $analytics, EvaluationCalibrationService $calibration)
    {
        $this->analytics = $analytics;
        $this->calibration = $calibration;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $showInternalDashboard = ! $user->hasAnyRole(['agent', 'supervisor']);
        $availableTabs = ['calidad', 'mp', 'feedback', 'ranking'];

        if ($showInternalDashboard) {
            $availableTabs = ['calidad', 'mp', 'feedback', 'calibracion', 'ranking', 'gestion'];
        }

        $activeTab = $request->query('tab', 'calidad');

        if (! in_array($activeTab, $availableTabs, true)) {
            $activeTab = 'calidad';
        }

        $filters = [
            'start_date' => $request->input('start_date', now()->startOfMonth()->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
            'campaign_id' => $request->input('campaign_id'),
            'parent_campaign_id' => $request->input('parent_campaign_id'),
        ];

        $stats = $this->analytics->getOverviewStats($filters);
        $campaigns = Campaign::forUser(auth()->user())->orderedForSelect()->get();

        $data = compact('stats', 'filters', 'campaigns', 'activeTab', 'availableTabs');

        match ($activeTab) {
            'mp' => $data += [
                'mpCampaign' => $this->analytics->getMPGrouped('campaign', $filters),
                'mpSupervisor' => $showInternalDashboard ? $this->analytics->getMPGrouped('supervisor', $filters) : [],
                'mpTrendSeries' => $this->analytics->getMpTrendSeries($filters),
                'topDefects' => $this->analytics->getTopDefects($filters, 10, true),
            ],
            'feedback' => $data += [
                'feedbackStats' => $this->analytics->getFeedbackStats($filters),
                'feedbackSupervisor' => $showInternalDashboard ? $this->analytics->getFeedbackBySupervisor($filters) : [],
                'feedbackTrendSeries' => $this->analytics->getFeedbackTrendSeries($filters),
            ],
            'calibracion' => $data += [
                'calibrationSummary' => $this->calibration->summary($filters, $user),
                'calibrationPairs' => $this->calibration->recentPairs($filters, $user),
            ],
            'ranking' => $data += [
                'agentRanking' => $this->analytics->getAgentRanking($filters),
            ],
            'gestion' => $data += [
                'evalsByCampaign' => $this->analytics->getEvalsByCampaign($filters),
                'agentRanking' => $this->analytics->getAgentRanking($filters),
                'qualityTrendSeries' => $this->analytics->getQualityTrendSeries($filters),
                'audioPerformance' => $this->analytics->getAudioUploadPerformance($filters),
            ],
            default => $data += [
                'qualityCampaign' => $this->analytics->getQualityGrouped('campaign', $filters),
                'qualitySupervisor' => $showInternalDashboard ? $this->analytics->getQualityGrouped('supervisor', $filters) : [],
                'qualityTrendSeries' => $this->analytics->getQualityTrendSeries($filters),
                'topDefects' => $this->analytics->getTopDefects($filters),
            ],
        };

        return view('dashboard.quality.index', $data);
    }
}
