<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::with('activeFormVersion')->latest()->paginate(15);
        return view('campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        return view('campaigns.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
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
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('campaigns/logos', 'public');
            $validated['logo_path'] = $path;
        }

        $campaign = Campaign::create($validated);

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Campaña creada exitosamente.');
    }

    public function show(Campaign $campaign)
    {
        $campaign->load(['activeFormVersion', 'assignments.agent', 'assignments.supervisor']);
        
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
        return view('campaigns.edit', compact('campaign'));
    }

    public function update(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
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
        ]);

        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($campaign->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($campaign->logo_path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($campaign->logo_path);
            }
            $path = $request->file('logo')->store('campaigns/logos', 'public');
            $validated['logo_path'] = $path;
        }

        $campaign->update($validated);

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Campaña actualizada exitosamente.');
    }

    public function destroy(Campaign $campaign)
    {
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
                    if ($interaction->file_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($interaction->file_path)) {
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($interaction->file_path);
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
}
