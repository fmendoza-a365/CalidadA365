<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignUserAssignment;
use App\Models\User;
use Illuminate\Http\Request;

class CampaignUserAssignmentController extends Controller
{
    /**
     * Display a listing of assignments for a campaign.
     */
    public function index(Campaign $campaign)
    {
        $assignments = $campaign->assignments()
            ->with(['agent', 'supervisor'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('campaigns.assignments.index', compact('campaign', 'assignments'));
    }

    /**
     * Show the form for creating a new assignment.
     */
    public function create(Campaign $campaign)
    {
        $agents = User::role('agent')->orderBy('name')->get();
        $supervisors = User::role('supervisor')->orderBy('name')->get();

        return view('campaigns.assignments.create', compact('campaign', 'agents', 'supervisors'));
    }

    /**
     * Store a newly created assignment.
     */
    public function store(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'agent_id' => 'required|exists:users,id',
            'supervisor_id' => 'required|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        // Verificar que no exista ya una asignación activa para este agente en esta campaña
        $exists = CampaignUserAssignment::where('campaign_id', $campaign->id)
            ->where('agent_id', $validated['agent_id'])
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            return back()->withErrors(['agent_id' => 'Este asesor ya está asignado a esta campaña.'])->withInput();
        }

        $campaign->assignments()->create([
            'agent_id' => $validated['agent_id'],
            'supervisor_id' => $validated['supervisor_id'],
            'start_date' => $validated['start_date'] ?? now(),
            'end_date' => $validated['end_date'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Asesor asignado exitosamente.');
    }

    /**
     * Show the form for editing an assignment.
     */
    public function edit(CampaignUserAssignment $assignment)
    {
        $campaign = $assignment->campaign;
        $agents = User::role('agent')->orderBy('name')->get();
        $supervisors = User::role('supervisor')->orderBy('name')->get();

        return view('campaigns.assignments.edit', compact('campaign', 'assignment', 'agents', 'supervisors'));
    }

    /**
     * Update the specified assignment.
     */
    public function update(Request $request, CampaignUserAssignment $assignment)
    {
        $validated = $request->validate([
            'supervisor_id' => 'required|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $assignment->update([
            'supervisor_id' => $validated['supervisor_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'is_active' => $validated['is_active'] ?? false,
        ]);

        return redirect()->route('campaigns.show', $assignment->campaign)
            ->with('success', 'Asignación actualizada exitosamente.');
    }

    /**
     * Remove the specified assignment.
     */
    public function destroy(CampaignUserAssignment $assignment)
    {
        $campaign = $assignment->campaign;
        $assignment->delete();

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Asignación eliminada exitosamente.');
    }
}
