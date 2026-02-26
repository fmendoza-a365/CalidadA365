<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Interaction;

class ManualEvaluationController extends Controller
{
    public function create(Interaction $interaction)
    {
        // Ensure there isn't already a manual evaluation
        if ($interaction->manualEvaluation()->exists()) {
            return redirect()->route('evaluations.show', $interaction->manualEvaluation)->with('warning', 'Esta interacción ya tiene una evaluación manual.');
        }

        $campaign = $interaction->campaign;
        // Asumiendo que la campaña tiene un form versions activo. 
        // Si no, usar la última versión disponible global o de la campaña.
        if ($interaction->quality_form_id) {
            $formVersion = $interaction->qualityForm->versions()
                ->where('status', 'published')
                ->latest('version_number')
                ->first();
        } else {
            $formVersion = $campaign->activeFormVersion;

            // Fallback: Si la campaña no tiene ficha activa configurada, usar la última publicada de cualquier ficha de la campaña
            if (!$formVersion) {
                $latestForm = $campaign->forms()->whereHas('versions', function ($q) {
                    $q->where('status', 'published');
                })->first();

                if ($latestForm) {
                    $formVersion = $latestForm->versions()
                        ->where('status', 'published')
                        ->latest('version_number')
                        ->first();
                }
            }

            // Último recurso: buscar global (si aplica)
            if (!$formVersion) {
                $formVersion = \App\Models\QualityFormVersion::where('is_active', true)->latest()->first();
            }
        }

        if (!$formVersion) {
            return back()->with('error', 'No hay una ficha de calidad activa para evaluar esta campaña.');
        }

        $formVersion->load('formAttributes.subAttributes');

        $aiEvaluation = $interaction->aiEvaluation()->with('items')->first();

        return view('evaluations.create_manual', compact('interaction', 'formVersion', 'aiEvaluation'));
    }

    public function store(Request $request, Interaction $interaction)
    {
        $validated = $request->validate([
            'form_version_id' => 'required|exists:quality_form_versions,id',
            'items' => 'required|array',
            'items.*.subattribute_id' => 'required|exists:quality_subattributes,id',
            'items.*.status' => 'required|in:compliant,non_compliant,not_found',
            'items.*.notes' => 'nullable|string',
        ]);

        $evaluation = \DB::transaction(function () use ($request, $interaction, $validated) {
            $formVersion = \App\Models\QualityFormVersion::findOrFail($validated['form_version_id']);

            // Calculate scores
            $totalPossible = 0;
            $totalScore = 0;
            $hasCriticalFailure = false;
            $itemsData = [];

            foreach ($validated['items'] as $itemData) {
                $subAttribute = \App\Models\QualitySubAttribute::find($itemData['subattribute_id']);

                $attribute = $subAttribute->attribute;
                $effectiveWeight = ($attribute->weight * $subAttribute->weight_percent) / 100;

                $itemScore = match ($itemData['status']) {
                    'compliant' => $effectiveWeight,
                    'non_compliant' => 0,
                    'not_found' => 0,
                };

                $itemMaxScore = $effectiveWeight;

                // Mala Práctica: marcar knockout
                if ($subAttribute->is_critical && $itemData['status'] === 'non_compliant') {
                    $hasCriticalFailure = true;
                }

                // Solo sumar items no-críticos al total
                if (!$subAttribute->is_critical) {
                    $totalScore += $itemScore;
                    $totalPossible += $itemMaxScore;
                }

                $itemsData[] = [
                    'subattribute_id' => $subAttribute->id,
                    'status' => $itemData['status'],
                    'score' => $subAttribute->is_critical ? 0 : $itemScore,
                    'max_score' => $subAttribute->is_critical ? 0 : $itemMaxScore,
                    'weighted_score' => $subAttribute->is_critical ? 0 : $itemScore,
                    'confidence' => 1.0,
                    'ai_notes' => $itemData['notes'] ?? null,
                ];
            }

            $percentage = $totalPossible > 0 ? ($totalScore / $totalPossible) * 100 : 0;

            // Knockout: mala práctica detectada
            if ($hasCriticalFailure) {
                $percentage = 0;
            }

            $evaluation = \App\Models\Evaluation::create([
                'interaction_id' => $interaction->id,
                'form_version_id' => $formVersion->id,
                'campaign_id' => $interaction->campaign_id,
                'agent_id' => $interaction->agent_id,
                'type' => 'manual',
                'evaluator_id' => auth()->id(),
                'total_score' => $totalScore,
                'max_possible_score' => $totalPossible,
                'percentage_score' => round($percentage, 2),
                'status' => 'visible_to_agent',
                'finalized_at' => now(),
            ]);

            $evaluation->items()->createMany($itemsData);

            return $evaluation;
        });

        return redirect()->route('evaluations.show', $evaluation)->with('success', 'Evaluación manual creada exitosamente.');
    }
}
