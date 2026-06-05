<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $campaigns = Campaign::forUser($user)
            ->with(['activeFormVersion', 'parent'])
            ->latest()
            ->paginate(15);
        return view('campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        $monitors = \App\Models\User::role(['qa_monitor', 'qa_coordinator', 'manager'])->orderBy('name')->get();
        $parentCampaigns = Campaign::active()->parents()->orderBy('name')->get();

        return view('campaigns.create', compact('monitors', 'parentCampaigns'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:campaigns,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'logo' => 'nullable|image|max:2048', // 2MB Max
            'color' => 'nullable|string|max:7',
            'type' => 'required|string|max:50',
            'target_quality' => 'nullable|numeric|between:0,100',
            'target_aht' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'script_url' => 'nullable|url',
            'monitor_ids' => 'nullable|array',
            'monitor_ids.*' => 'exists:users,id',
        ]);

        $this->ensureCampaignManagerRoles($validated['monitor_ids'] ?? []);
        $this->ensureValidParent($validated['parent_id'] ?? null);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('campaigns/logos', 'public');
            $validated['logo_path'] = $path;
        }

        $campaign = Campaign::create($validated);

        if ($request->has('monitor_ids')) {
            $campaign->managers()->sync($request->monitor_ids);
        }

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Campaña creada exitosamente.');
    }

    public function show(Campaign $campaign)
    {
        $this->ensureCampaignAccess($campaign);

        $campaign->load(['activeFormVersion', 'assignments.agent', 'assignments.supervisor', 'managers', 'parent', 'children.activeFormVersion']);

        $stats = [
            'total_interactions' => $campaign->interactions()->count(),
            'total_evaluations' => $campaign->evaluations()->count(),
            'avg_score' => round($campaign->evaluations()->avg('percentage_score') ?? 0, 2),
            'active_agents' => $campaign->assignments()->active()->count(),
        ];

        return view('campaigns.show', compact('campaign', 'stats'));
    }

    public function edit(Campaign $campaign)
    {
        $this->ensureCampaignAccess($campaign);

        $monitors = \App\Models\User::role(['qa_monitor', 'qa_coordinator', 'manager'])->orderBy('name')->get();
        $campaign->load('managers');
        $parentCampaigns = Campaign::active()
            ->parents()
            ->whereKeyNot($campaign->id)
            ->orderBy('name')
            ->get();

        return view('campaigns.edit', compact('campaign', 'monitors', 'parentCampaigns'));
    }

    public function update(Request $request, Campaign $campaign)
    {
        $this->ensureCampaignAccess($campaign);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:campaigns,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'logo' => 'nullable|image|max:2048',
            'color' => 'nullable|string|max:7',
            'type' => 'required|string|max:50',
            'target_quality' => 'nullable|numeric|between:0,100',
            'target_aht' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'script_url' => 'nullable|url',
            'monitor_ids' => 'nullable|array',
            'monitor_ids.*' => 'exists:users,id',
        ]);

        $this->ensureCampaignManagerRoles($validated['monitor_ids'] ?? []);
        $this->ensureValidParent($validated['parent_id'] ?? null, $campaign);

        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($campaign->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($campaign->logo_path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($campaign->logo_path);
            }
            $path = $request->file('logo')->store('campaigns/logos', 'public');
            $validated['logo_path'] = $path;
        }

        $campaign->update($validated);

        if ($request->has('monitor_ids')) {
            $campaign->managers()->sync($request->monitor_ids);
        } else {
            $campaign->managers()->detach();
        }

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Campaña actualizada exitosamente.');
    }

    public function destroy(Campaign $campaign)
    {
        $this->ensureCampaignAccess($campaign);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($campaign) {
                // 1. Eliminar evaluaciones (y sus items por cascada de BD si está configurado, o manual)
                $campaign->evaluations()->each(function ($evaluation) {
                    $evaluation->items()->delete();
                    $evaluation->delete();
                });

                // 2. Eliminar interacciones (transcripciones)
                $campaign->interactions()->each(function ($interaction) {
                    // Eliminar archivo físico si existe
                    $privateDisk = config('filesystems.default', 'local');
                    if ($interaction->file_path && \Illuminate\Support\Facades\Storage::disk($privateDisk)->exists($interaction->file_path)) {
                        \Illuminate\Support\Facades\Storage::disk($privateDisk)->delete($interaction->file_path);
                    }
                    $interaction->delete();
                });

                // 3. Eliminar asignaciones de usuarios
                $campaign->assignments()->delete();

                // 4. Desvincular ficha activa para evitar error de FK cíclica si existe
                $campaign->update(['active_form_version_id' => null]);

                // 5. Eliminar fichas de calidad asociadas
                $campaign->forms()->each(function ($form) {
                    $form->versions()->each(function ($version) {
                        $version->formAttributes()->each(function ($attr) {
                            $attr->subAttributes()->delete();
                            $attr->delete();
                        });
                        $version->delete();
                    });
                    $form->delete();
                });

                // 6. Eliminar el logo si existe
                if ($campaign->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($campaign->logo_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($campaign->logo_path);
                }

                // 7. Finalmente eliminar la campaña
                $campaign->delete();
            });

            return redirect()->route('campaigns.index')
                ->with('success', 'Campaña y todos sus datos asociados eliminados exitosamente.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error eliminando campaña: ' . $e->getMessage());
            return back()->with('error', 'No se pudo eliminar la campaña: ' . $e->getMessage());
        }
    }

    private function ensureCampaignAccess(Campaign $campaign): void
    {
        if (!Campaign::forUser(auth()->user())->whereKey($campaign->id)->exists()) {
            abort(403, 'No tiene permiso para gestionar esta campaña.');
        }
    }

    private function ensureCampaignManagerRoles(array $userIds): void
    {
        if (empty($userIds)) {
            return;
        }

        $allowedRoles = ['qa_monitor', 'qa_coordinator', 'manager'];
        $invalidNames = User::whereIn('id', $userIds)
            ->whereDoesntHave('roles', function ($query) use ($allowedRoles) {
                $query->whereIn('name', $allowedRoles);
            })
            ->pluck('name')
            ->all();

        if (!empty($invalidNames)) {
            throw ValidationException::withMessages([
                'monitor_ids' => 'Solo se pueden asignar monitores, coordinadores QA o managers a una campaña. Usuarios inválidos: ' . implode(', ', $invalidNames),
            ]);
        }
    }

    private function ensureValidParent(?int $parentId, ?Campaign $campaign = null): void
    {
        if (! $parentId) {
            return;
        }

        if ($campaign && $campaign->id === $parentId) {
            throw ValidationException::withMessages([
                'parent_id' => 'Una campaña no puede ser su propia campaña general.',
            ]);
        }

        $parent = Campaign::query()->find($parentId);

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_id' => 'La campaña general seleccionada no existe.',
            ]);
        }

        if ($parent->parent_id !== null) {
            throw ValidationException::withMessages([
                'parent_id' => 'Selecciona una campaña general, no otra subcampaña.',
            ]);
        }
    }
}
