<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\QualityFormVersion;
use App\Models\QualitySubAttribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManualEvaluationController extends Controller
{
    public function create(Interaction $interaction)
    {
        $this->authorizeManualEvaluation($interaction);

        if ($manualEvaluation = $interaction->manualEvaluation()->first()) {
            return redirect()
                ->route('evaluations.show', $manualEvaluation)
                ->with('warning', 'Esta interacción ya tiene una evaluación manual.');
        }

        $formVersion = $interaction->scorableFormVersion();

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

        if ($manualEvaluation = $interaction->manualEvaluation()->first()) {
            return redirect()
                ->route('evaluations.show', $manualEvaluation)
                ->with('warning', 'Esta interacción ya tiene una evaluación manual.');
        }

        $validated = $request->validate([
            'form_version_id' => 'required|exists:quality_form_versions,id',
            'items' => 'required|array',
            'items.*.subattribute_id' => 'required|exists:quality_subattributes,id',
            'items.*.status' => 'required|in:compliant,non_compliant,not_found',
            'items.*.notes' => 'nullable|string',
        ]);

        $evaluation = DB::transaction(function () use ($interaction, $validated) {
            $formVersion = QualityFormVersion::findOrFail($validated['form_version_id']);

            if ($formVersion->form->campaign_id !== $interaction->campaign_id) {
                abort(422, 'La ficha seleccionada no pertenece a la campaña de la interacción.');
            }

            $subAttributes = QualitySubAttribute::query()
                ->with('attribute')
                ->whereHas('attribute', fn ($query) => $query->where('form_version_id', $formVersion->id))
                ->get()
                ->keyBy('id');

            $submittedIds = collect($validated['items'])
                ->pluck('subattribute_id')
                ->map(fn ($id) => (int) $id);

            $missingIds = $subAttributes->keys()->diff($submittedIds);
            if ($missingIds->isNotEmpty()) {
                abort(422, 'Debe completar todos los criterios de la ficha antes de guardar.');
            }

            // Calculate scores
            $totalPossible = 0;
            $totalScore = 0;
            $hasCriticalFailure = false;
            $itemsData = [];

            foreach ($validated['items'] as $itemData) {
                $subAttribute = $subAttributes->get((int) $itemData['subattribute_id']);

                if (! $subAttribute) {
                    abort(422, 'Uno de los criterios enviados no pertenece a la ficha seleccionada.');
                }

                $attribute = $subAttribute->attribute;
                $effectiveWeight = ($attribute->weight * $subAttribute->weight_percent) / 100;

                $scoreRatio = match ($itemData['status']) {
                    'compliant' => 1.0,
                    'non_compliant' => 0.0,
                    'not_found' => null,
                };

                // Mala Práctica: marcar knockout
                if ($subAttribute->is_critical && $itemData['status'] === 'non_compliant') {
                    $hasCriticalFailure = true;
                }

                $weightedScore = 0;
                $maxScore = 0;

                // N/A no penaliza ni suma al denominador; críticos solo funcionan como knockout.
                if (! $subAttribute->is_critical && $scoreRatio !== null) {
                    $weightedScore = $scoreRatio * $effectiveWeight;
                    $maxScore = 1;
                    $totalScore += $weightedScore;
                    $totalPossible += $effectiveWeight;
                }

                $itemsData[] = [
                    'subattribute_id' => $subAttribute->id,
                    'status' => $itemData['status'],
                    'score' => $scoreRatio ?? 0,
                    'max_score' => $maxScore,
                    'weighted_score' => $weightedScore,
                    'confidence' => 1.0,
                    'ai_notes' => $itemData['notes'] ?? null,
                ];
            }

            $percentage = $totalPossible > 0 ? ($totalScore / $totalPossible) * 100 : 0;

            // Knockout: mala práctica detectada
            if ($hasCriticalFailure) {
                $percentage = 0;
            }

            $evaluation = Evaluation::create([
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
                'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
                'visible_to_agent_at' => now(),
                'finalized_at' => now(),
            ]);

            $evaluation->items()->createMany($itemsData);

            $evaluation->recordAuditEvent('manual_created', auth()->user(), [
                'form_version_id' => $formVersion->id,
                'items_count' => count($itemsData),
                'percentage_score' => $evaluation->percentage_score,
                'has_critical_failure' => $hasCriticalFailure,
            ], null, Evaluation::STATUS_PUBLISHED_TO_AGENT);

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

        if (! $user->can('create_evaluations') && ! $user->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator', 'qa_monitor'])) {
            abort(403, 'No tiene permiso para crear evaluaciones manuales.');
        }

        if ($user->hasRole('admin')) {
            return;
        }

        $canAccessInteraction = Interaction::forUser($user)->whereKey($interaction->id)->exists();
        $canAccessEvaluation = Evaluation::forUser($user)
            ->where('interaction_id', $interaction->id)
            ->exists();
        $isUploader = (int) $interaction->uploaded_by === (int) $user->id;
        $isEvaluator = $interaction->evaluations()
            ->where('evaluator_id', $user->id)
            ->exists();

        if (! $canAccessInteraction && ! $canAccessEvaluation && ! $isUploader && ! $isEvaluator) {
            abort(403, 'No tiene permiso para evaluar esta interacción.');
        }
    }
}
