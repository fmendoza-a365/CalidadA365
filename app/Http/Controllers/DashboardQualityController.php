<?php

namespace App\Http\Controllers;

use App\Services\QualityAnalyticsService;
use App\Models\Campaign;
use Illuminate\Http\Request;

class DashboardQualityController extends Controller
{
    protected QualityAnalyticsService $analytics;

    public function __construct(QualityAnalyticsService $analytics)
    {
        $this->analytics = $analytics;
    }

    public function index(Request $request)
    {
        $filters = [
            'start_date' => $request->input('start_date', now()->startOfMonth()->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
            'campaign_id' => $request->input('campaign_id'),
        ];

        // Tab 1: Dashboard Calidad
        $stats = $this->analytics->getOverviewStats($filters);
        $qualityMonth = $this->analytics->getQualityGrouped('month', $filters);
        $qualityWeek = $this->analytics->getQualityGrouped('week', $filters);
        $qualityCampaign = $this->analytics->getQualityGrouped('campaign', $filters);
        $qualitySupervisor = $this->analytics->getQualityGrouped('supervisor', $filters);
        $qualityDaily = $this->analytics->getQualityGrouped('daily', $filters);

        // Tab 2: Malas Prácticas
        $mpMonth = $this->analytics->getMPGrouped('month', $filters);
        $mpWeek = $this->analytics->getMPGrouped('week', $filters);
        $mpCampaign = $this->analytics->getMPGrouped('campaign', $filters);
        $mpSupervisor = $this->analytics->getMPGrouped('supervisor', $filters);
        $mpDaily = $this->analytics->getMPGrouped('daily', $filters);

        // Tab 3: Seguimiento Feedback
        $feedbackStats = $this->analytics->getFeedbackStats($filters);
        $feedbackSupervisor = $this->analytics->getFeedbackBySupervisor($filters);
        $feedbackWeek = $this->analytics->getFeedbackByWeek($filters);

        // Tab 4/5: Rankings & Defects
        $agentRanking = $this->analytics->getAgentRanking($filters);
        $topDefects = $this->analytics->getTopDefects($filters);
        $evalsByCampaign = $this->analytics->getEvalsByCampaign($filters);

        $campaigns = Campaign::all();

        return view('dashboard.quality.index', compact(
            'stats',
            'qualityMonth',
            'qualityWeek',
            'qualityCampaign',
            'qualitySupervisor',
            'qualityDaily',
            'mpMonth',
            'mpWeek',
            'mpCampaign',
            'mpSupervisor',
            'mpDaily',
            'feedbackStats',
            'feedbackSupervisor',
            'feedbackWeek',
            'agentRanking',
            'topDefects',
            'evalsByCampaign',
            'filters',
            'campaigns'
        ));
    }
}
