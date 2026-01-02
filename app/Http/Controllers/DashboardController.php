<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\DisputeResolution;
use App\Models\CampaignUserAssignment;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // All users now get the customizable dashboard
        return view('dashboard.custom');
        
        /* Legacy role-based dashboards (kept for reference)
        $user = auth()->user();

        if ($user->hasRole('admin') || $user->hasRole('qa_manager')) {
            return $this->adminDashboard();
        } elseif ($user->hasRole('supervisor')) {
            return $this->supervisorDashboard($user);
        } else {
            return $this->agentDashboard($user);
        }
        */
    }

    private function adminDashboard()
    {
        $stats = [
            'total_evaluations' => Evaluation::count(),
            'avg_score' => round(Evaluation::avg('percentage_score') ?? 0, 2),
            'pending_disputes' => DisputeResolution::whereNull('resolved_at')->count(),
            'campaigns_active' => Campaign::active()->count(),
        ];

        $recentEvaluations = Evaluation::with(['agent', 'campaign'])
            ->latest()
            ->limit(10)
            ->get();

        $topFailures = DB::table('evaluation_items')
            ->join('quality_subattributes', 'evaluation_items.subattribute_id', '=', 'quality_subattributes.id')
            ->where('evaluation_items.status', 'non_compliant')
            ->select('quality_subattributes.name', DB::raw('count(*) as count'))
            ->groupBy('quality_subattributes.id', 'quality_subattributes.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return view('dashboard.admin', compact('stats', 'recentEvaluations', 'topFailures'));
    }

    private function supervisorDashboard($user)
    {
        $teamAgents = CampaignUserAssignment::where('supervisor_id', $user->id)
            ->where('is_active', true)
            ->pluck('agent_id');

        $stats = [
            'team_size' => $teamAgents->count(),
            'team_avg_score' => round(Evaluation::whereIn('agent_id', $teamAgents)->avg('percentage_score') ?? 0, 2),
            'evaluations_this_week' => Evaluation::whereIn('agent_id', $teamAgents)
                ->whereBetween('created_at', [now()->startOfWeek(), now()])
                ->count(),
            'pending_responses' => Evaluation::whereIn('agent_id', $teamAgents)
                ->where('status', 'visible_to_agent')
                ->count(),
        ];

        $teamPerformance = Evaluation::whereIn('agent_id', $teamAgents)
            ->with('agent')
            ->select('agent_id', DB::raw('AVG(percentage_score) as avg_score'), DB::raw('COUNT(*) as total'))
            ->groupBy('agent_id')
            ->get();

        return view('dashboard.supervisor', compact('stats', 'teamPerformance'));
    }

    private function agentDashboard($user)
    {
        $stats = [
            'my_avg_score' => round(Evaluation::where('agent_id', $user->id)->avg('percentage_score') ?? 0, 2),
            'evaluations_count' => Evaluation::where('agent_id', $user->id)->count(),
            'pending_responses' => Evaluation::where('agent_id', $user->id)
                ->where('status', 'visible_to_agent')
                ->count(),
            'this_month_avg' => round(Evaluation::where('agent_id', $user->id)
                ->whereMonth('created_at', now()->month)
                ->avg('percentage_score') ?? 0, 2),
        ];

        $recentEvaluations = Evaluation::where('agent_id', $user->id)
            ->with(['campaign', 'formVersion'])
            ->visibleToAgent()
            ->latest()
            ->limit(10)
            ->get();

        return view('dashboard.agent', compact('stats', 'recentEvaluations'));
    }
}
