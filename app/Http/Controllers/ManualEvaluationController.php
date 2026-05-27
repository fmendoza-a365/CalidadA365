<?php

namespace App\Http\Controllers;

use App\Models\Interaction;
use Illuminate\Http\Request;

class ManualEvaluationController extends Controller
{
    public function create(Interaction $interaction)
    {
        $this->authorizeManualEvaluation($interaction);

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
            if (! $formVersion) {
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
        }

        if (! $formVersion) {
            return back()->with('error', 'No hay una ficha de calidad activa para evaluar esta campaña.');
        }

        $formVersion->load('formAttributes.subAttributes');

        $aiEvaluation = $interaction->aiEvaluation()->with('items')->first();

        return view('evaluations.create_manual', compact('interaction', 'formVersion', 'aiEvaluation'));
    }

    public function store(Request $request, Interaction $interaction)
    {
        $this->authorizeManualEvaluation($interaction);

        $validated = $request->validate([
            'form_version_id' => 'required|exists:quality_form_versions,id',
            'items' => 'required|array',
            'items.*.subattribute_id' => 'required|exists:quality_subattributes,id',
            'items.*.status' => 'required|in:compliant,non_compliant,not_found',
            'items.*.notes' => 'nullable|string',
        ]);

        $evaluation = \DB::transaction(function () use ($interaction, $validated) {
            $formVersion = \App\Models\QualityFormVersion::findOrFail($validated['form_version_id']);

            if ($formVersion->form->campaign_id !== $interaction->campaign_id) {
                abort(422, 'La ficha seleccionada no pertenece a la campaña de la interacción.');
            }

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
                if (! $subAttribute->is_critical) {
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
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'published_by' => auth()->id(),
                'total_score' => $totalScore,
                'max_possible_score' => $totalPossible,
                'percentage_score' => round($percentage, 2),
                'status' => \App\Models\Evaluation::STATUS_PUBLISHED_TO_AGENT,
                'visible_to_agent_at' => now(),
                'finalized_at' => now(),
            ]);

            $evaluation->items()->createMany($itemsData);

            $evaluation->recordAuditEvent('manual_created', auth()->user(), [
                'form_version_id' => $formVersion->id,
                'items_count' => count($itemsData),
                'percentage_score' => $evaluation->percentage_score,
                'has_critical_failure' => $hasCriticalFailure,
            ], null, \App\Models\Evaluation::STATUS_PUBLISHED_TO_AGENT);

            return $evaluation;
        });

        if ($evaluation->agent) {
            $evaluation->agent->notify(new \App\Notifications\EvaluationCompleted($evaluation));
        }

        return redirect()->route('evaluations.show', $evaluation)->with('success', 'Evaluación manual creada exitosamente.');
    }

    private function authorizeManualEvaluation(Interaction $interaction): void
    {
        $user = auth()->user();

        if (! $user->can('create_evaluations') && ! $user->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator'])) {
            abort(403, 'No tiene permiso para crear evaluaciones manuales.');
        }

        if (! Interaction::forUser($user)->whereKey($interaction->id)->exists()) {
            abort(403, 'No tiene permiso para evaluar esta interacción.');
        }
    }
}
