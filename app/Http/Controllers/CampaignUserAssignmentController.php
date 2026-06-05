<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignUserAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CampaignUserAssignmentController extends Controller
{
    /**
     * Display a listing of assignments for a campaign.
     */
    public function index(Campaign $campaign)
    {
        $this->ensureCampaignAccess($campaign);

        $campaignIds = Campaign::idsForFilter($campaign->id);

        $assignments = CampaignUserAssignment::query()
            ->whereIn('campaign_id', $campaignIds)
            ->with(['campaign.parent', 'agent', 'supervisor'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('campaigns.assignments.index', compact('campaign', 'assignments'));
    }

    /**
     * Show the form for creating a new assignment.
     */
    public function create(Campaign $campaign)
    {
        $this->ensureCampaignAccess($campaign);

        $agents = User::role('agent')->orderBy('name')->get();
        $supervisors = User::role('supervisor')->orderBy('name')->get();
        $assignmentCampaigns = $this->assignmentCampaignOptions($campaign);

        return view('campaigns.assignments.create', compact('campaign', 'agents', 'supervisors', 'assignmentCampaigns'));
    }

    /**
     * Store a newly created assignment.
     */
    public function store(Request $request, Campaign $campaign)
    {
        $this->ensureCampaignAccess($campaign);

        $validated = $request->validate([
            'agent_id' => 'required|exists:users,id',
            'supervisor_id' => 'required|exists:users,id',
            'target_campaign_id' => 'nullable|exists:campaigns,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $this->ensureUserHasRole($validated['agent_id'], 'agent', 'agent_id', 'El usuario seleccionado debe tener rol asesor.');
        $this->ensureUserHasRole($validated['supervisor_id'], 'supervisor', 'supervisor_id', 'El usuario seleccionado debe tener rol supervisor.');
        $targetCampaign = $this->resolveAssignmentCampaign($campaign, $validated['target_campaign_id'] ?? null);

        // Verificar que no exista ya una asignación activa para este agente en esta campaña
        $exists = CampaignUserAssignment::where('campaign_id', $targetCampaign->id)
            ->where('agent_id', $validated['agent_id'])
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            return back()->withErrors(['agent_id' => 'Este asesor ya está asignado a esta campaña o subcampaña.'])->withInput();
        }

        $targetCampaign->assignments()->create([
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
        $this->ensureCampaignAccess($campaign);

        $agents = User::role('agent')->orderBy('name')->get();
        $supervisors = User::role('supervisor')->orderBy('name')->get();
        $contextCampaign = $campaign->parent ?: $campaign;
        $assignmentCampaigns = $this->assignmentCampaignOptions($contextCampaign);

        return view('campaigns.assignments.edit', compact('campaign', 'assignment', 'agents', 'supervisors', 'assignmentCampaigns', 'contextCampaign'));
    }

    /**
     * Update the specified assignment.
     */
    public function update(Request $request, CampaignUserAssignment $assignment)
    {
        $this->ensureCampaignAccess($assignment->campaign);

        $validated = $request->validate([
            'supervisor_id' => 'required|exists:users,id',
            'target_campaign_id' => 'nullable|exists:campaigns,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $this->ensureUserHasRole($validated['supervisor_id'], 'supervisor', 'supervisor_id', 'El usuario seleccionado debe tener rol supervisor.');
        $contextCampaign = $assignment->campaign->parent ?: $assignment->campaign;
        $targetCampaign = $this->resolveAssignmentCampaign($contextCampaign, $validated['target_campaign_id'] ?? $assignment->campaign_id);

        $assignment->update([
            'campaign_id' => $targetCampaign->id,
            'supervisor_id' => $validated['supervisor_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'is_active' => $validated['is_active'] ?? false,
        ]);

        return redirect()->route('campaigns.show', $targetCampaign->parent ?: $targetCampaign)
            ->with('success', 'Asignación actualizada exitosamente.');
    }

    /**
     * Remove the specified assignment.
     */
    public function destroy(CampaignUserAssignment $assignment)
    {
        $campaign = $assignment->campaign;
        $this->ensureCampaignAccess($campaign);

        $assignment->delete();

        return redirect()->route('campaigns.show', $campaign->parent ?: $campaign)
            ->with('success', 'Asignación eliminada exitosamente.');
    }

    private function ensureCampaignAccess(Campaign $campaign): void
    {
        if (!Campaign::forUser(auth()->user())->whereKey($campaign->id)->exists()) {
            abort(403, 'No tiene permiso para gestionar asignaciones de esta campaña.');
        }
    }

    private function ensureUserHasRole(int $userId, string $role, string $field, string $message): void
    {
        if (!User::role($role)->whereKey($userId)->exists()) {
            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }
    }

    private function assignmentCampaignOptions(Campaign $campaign)
    {
        $campaign->loadMissing('children.parent', 'parent');

        if ($campaign->parent_id) {
            return collect([$campaign->loadMissing('parent')]);
        }

        $children = $campaign->children()
            ->active()
            ->with('parent')
            ->orderBy('name')
            ->get();

        return $children->isNotEmpty()
            ? $children
            : collect([$campaign]);
    }

    private function resolveAssignmentCampaign(Campaign $campaign, ?int $targetCampaignId): Campaign
    {
        $options = $this->assignmentCampaignOptions($campaign);

        if ($options->count() === 1 && ! $targetCampaignId) {
            return $options->first();
        }

        if (! $targetCampaignId) {
            throw ValidationException::withMessages([
                'target_campaign_id' => 'Selecciona la subcampaña a la que se asignará el asesor.',
            ]);
        }

        $targetCampaign = $options->firstWhere('id', (int) $targetCampaignId);

        if (! $targetCampaign) {
            throw ValidationException::withMessages([
                'target_campaign_id' => 'La subcampaña seleccionada no pertenece a esta campaña.',
            ]);
        }

        $this->ensureCampaignAccess($targetCampaign);

        return $targetCampaign;
    }
}
