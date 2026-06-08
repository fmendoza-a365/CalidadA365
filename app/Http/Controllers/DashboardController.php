<?php

namespace App\Http\Controllers;

use App\Models\Campaign;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->hasRole('agent')) {
            $analytics = app(\App\Services\QualityAnalyticsService::class);
            $filters = [
                'start_date' => request('start_date', now()->startOfMonth()->format('Y-m-d')),
                'end_date' => request('end_date', now()->endOfMonth()->format('Y-m-d')),
                'campaign_id' => request('campaign_id'),
                'parent_campaign_id' => request('parent_campaign_id'),
            ];

            $stats = $analytics->getOverviewStats($filters);
            $league = $analytics->getAgentLeague($stats['average_score']);
            $matchHistory = $analytics->paginateAgentMatchHistory($filters, 10);
            $agentRanking = $analytics->getAgentRanking($filters);
            $topDefects = $analytics->getTopDefects($filters);
            $campaigns = Campaign::forUser($user)->orderedForSelect()->get();

            return view('dashboard.agent', compact(
                'stats',
                'league',
                'matchHistory',
                'agentRanking',
                'topDefects',
                'campaigns',
                'filters'
            ));
        }

        return redirect()->route('dashboard.quality');
    }
}
