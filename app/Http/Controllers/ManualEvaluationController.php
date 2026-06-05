<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\QualityFormVersion;
use App\Models\QualitySubAttribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

        $interaction->loadMissing(['agent', 'campaign.parent']);
        $formVersion->load('formAttributes.subAttributes');

        $aiEvaluation = $interaction->aiEvaluation()->with('items')->first();

        if ($aiEvaluation) {
            try {
                $aiEvaluation = $this->claimEvaluationForManualReview($aiEvaluation);
            } catch (RuntimeException $exception) {
                return redirect()
                    ->route('work-queue.index')
                    ->with('warning', $exception->getMessage());
            }
        }

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

        try {
            $result = DB::transaction(function () use ($interaction, $validated) {
                $lockedInteraction = Interaction::whereKey($interaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($manualEvaluation = $lockedInteraction->manualEvaluation()->first()) {
                    return ['existing' => $manualEvaluation];
                }

                $formVersion = QualityFormVersion::findOrFail($validated['form_version_id']);
                $aiEvaluation = Evaluation::query()
                    ->with(['items.subAttribute', 'reviewClaimer'])
                    ->where('interaction_id', $lockedInteraction->id)
                    ->where('type', 'ai')
                    ->lockForUpdate()
                    ->first();

                if ($aiEvaluation) {
                    $aiEvaluation = $this->reserveLockedEvaluationForManualReview($aiEvaluation);
                }

                $aiItemsBySubAttribute = $aiEvaluation?->items->keyBy('subattribute_id') ?? collect();

                if ($formVersion->form->campaign_id !== $lockedInteraction->campaign_id) {
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
                $summaryItems = [];

                foreach ($validated['items'] as $itemData) {
                    $subAttribute = $subAttributes->get((int) $itemData['subattribute_id']);

                    if (! $subAttribute) {
                        abort(422, 'Uno de los criterios enviados no pertenece a la ficha seleccionada.');
                    }

                    $attribute = $subAttribute->attribute;
                    $effectiveWeight = ($attribute->weight * $subAttribute->weight_percent) / 100;
                    $aiItem = $aiItemsBySubAttribute->get($subAttribute->id);
                    $monitorNote = trim((string) ($itemData['notes'] ?? '')) ?: null;

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
                        'evidence_quote' => $aiItem?->evidence_quote,
                        'evidence_reference' => $aiItem?->evidence_reference,
                        'confidence' => 1.0,
                        'ai_notes' => $monitorNote,
                    ];

                    $summaryItems[] = [
                        'criterion' => $subAttribute->name,
                        'manual_status' => $itemData['status'],
                        'ai_status' => $aiItem?->status,
                        'changed' => $aiItem && $aiItem->status !== $itemData['status'],
                        'note' => $monitorNote,
                    ];
                }

                $percentage = $totalPossible > 0 ? ($totalScore / $totalPossible) * 100 : 0;

                // Knockout: mala práctica detectada
                if ($hasCriticalFailure) {
                    $percentage = 0;
                }

                $manualSummary = $this->buildManualSummary(
                    $aiEvaluation,
                    $summaryItems,
                    round($percentage, 2),
                    $hasCriticalFailure
                );

                $evaluation = Evaluation::create([
                    'interaction_id' => $lockedInteraction->id,
                    'form_version_id' => $formVersion->id,
                    'campaign_id' => $lockedInteraction->campaign_id,
                    'agent_id' => $lockedInteraction->agent_id,
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
                    'ai_summary' => $manualSummary,
                ]);

                $evaluation->items()->createMany($itemsData);

                $evaluation->recordAuditEvent('manual_created', auth()->user(), [
                    'form_version_id' => $formVersion->id,
                    'items_count' => count($itemsData),
                    'percentage_score' => $evaluation->percentage_score,
                    'has_critical_failure' => $hasCriticalFailure,
                ], null, Evaluation::STATUS_PUBLISHED_TO_AGENT);

                $aiEvaluation?->releaseReviewClaim();

                return ['evaluation' => $evaluation];
            });
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('work-queue.index')
                ->with('warning', $exception->getMessage());
        }

        if ($manualEvaluation = $result['existing'] ?? null) {
            return redirect()
                ->route('evaluations.show', $manualEvaluation)
                ->with('warning', 'Esta interacción ya tiene una evaluación manual.');
        }

        $evaluation = $result['evaluation'];

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

    private function claimEvaluationForManualReview(Evaluation $evaluation): Evaluation
    {
        return DB::transaction(function () use ($evaluation) {
            $lockedEvaluation = Evaluation::with(['items.subAttribute', 'reviewClaimer'])
                ->whereKey($evaluation->id)
                ->lockForUpdate()
                ->firstOrFail();

            return $this->reserveLockedEvaluationForManualReview($lockedEvaluation);
        });
    }

    private function reserveLockedEvaluationForManualReview(Evaluation $evaluation): Evaluation
    {
        $user = auth()->user();
        $evaluation->loadMissing('reviewClaimer');

        if ($evaluation->isReviewClaimedByOther($user) && ! $user->hasRole('admin')) {
            throw new RuntimeException($this->reviewClaimConflictMessage($evaluation));
        }

        $shouldRecordClaim = ! $evaluation->isReviewClaimedBy($user);
        $expiresAt = now()->addMinutes($this->manualReviewClaimMinutes());

        $evaluation->claimForReview($user, $expiresAt);

        if ($shouldRecordClaim) {
            $evaluation->recordAuditEvent('manual_review_claimed', $user, [
                'review_claim_expires_at' => $expiresAt->toDateTimeString(),
            ], $evaluation->status, $evaluation->status);
        }

        $evaluation->unsetRelation('reviewClaimer');

        return $evaluation->load(['items.subAttribute', 'reviewClaimer']);
    }

    private function reviewClaimConflictMessage(Evaluation $evaluation): string
    {
        $evaluation->loadMissing('reviewClaimer');
        $claimer = $evaluation->reviewClaimer?->name ?? 'otro monitor';
        $expiresIn = $evaluation->review_claim_expires_at?->diffForHumans(null, true);

        return 'Esta evaluación está reservada por '.$claimer.($expiresIn ? ' durante '.$expiresIn.' más.' : '.');
    }

    private function manualReviewClaimMinutes(): int
    {
        return max(5, (int) config('evaluations.manual_review_claim_minutes', 30));
    }

    private function buildManualSummary(?Evaluation $aiEvaluation, array $items, float $percentage, bool $hasCriticalFailure): string
    {
        $statusLabels = [
            'compliant' => 'Cumple',
            'non_compliant' => 'No cumple',
            'not_found' => 'No encontrado',
        ];

        $items = collect($items);
        $changedItems = $items->where('changed', true)->values();
        $monitorNotes = $items
            ->filter(fn (array $item) => filled($item['note'] ?? null))
            ->take(8)
            ->values();

        $summary = "### Resumen de Corrección Manual\n";
        $summary .= 'El monitor ajustó la evaluación final a **'.number_format($percentage, 1).'%**.';

        if ($changedItems->isNotEmpty()) {
            $summary .= " Se modificaron **{$changedItems->count()}** criterio(s) respecto a la evaluación IA.";
        } else {
            $summary .= ' No hubo cambios de criterio respecto a la evaluación IA.';
        }

        if ($hasCriticalFailure) {
            $summary .= ' La evaluación quedó en 0% por incumplimiento crítico.';
        }

        $summary .= "\n\n### Criterios Corregidos\n";
        if ($changedItems->isEmpty()) {
            $summary .= "- Sin criterios corregidos.\n";
        } else {
            foreach ($changedItems as $item) {
                $aiStatus = $statusLabels[$item['ai_status']] ?? 'Sin dato';
                $manualStatus = $statusLabels[$item['manual_status']] ?? 'Sin dato';
                $summary .= "- **{$item['criterion']}**: IA {$aiStatus} -> Monitor {$manualStatus}";
                if (filled($item['note'] ?? null)) {
                    $summary .= ". Nota: {$item['note']}";
                }
                $summary .= "\n";
            }
        }

        if ($monitorNotes->isNotEmpty()) {
            $summary .= "\n### Notas del Monitor\n";
            foreach ($monitorNotes as $item) {
                $summary .= "- **{$item['criterion']}**: {$item['note']}\n";
            }
        }

        if (filled($aiEvaluation?->ai_summary)) {
            $summary .= "\n### Feedback Original de IA\n";
            $summary .= $aiEvaluation->ai_summary;
        }

        return $summary;
    }
}
