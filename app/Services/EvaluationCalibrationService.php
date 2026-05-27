<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EvaluationCalibrationService
{
    private const HIGH_DELTA_THRESHOLD = 10;

    public function compareForEvaluation(Evaluation $evaluation): ?array
    {
        $evaluation->loadMissing([
            'agent',
            'campaign',
            'items.subAttribute',
            'interaction.evaluations.items.subAttribute',
        ]);

        $counterpartType = $evaluation->type === 'ai' ? 'manual' : 'ai';
        $counterpart = $evaluation->interaction?->evaluations
            ->firstWhere('type', $counterpartType);

        if (! $counterpart) {
            return null;
        }

        $aiEvaluation = $evaluation->type === 'ai' ? $evaluation : $counterpart;
        $manualEvaluation = $evaluation->type === 'manual' ? $evaluation : $counterpart;

        return $this->compareEvaluations($aiEvaluation, $manualEvaluation);
    }

    public function summary(array $filters, User $user): array
    {
        $comparisons = $this->comparisons($filters, $user);
        $pairCount = $comparisons->count();

        if ($pairCount === 0) {
            return [
                'pair_count' => 0,
                'average_ai_score' => 0,
                'average_manual_score' => 0,
                'average_score_delta' => 0,
                'average_absolute_delta' => 0,
                'item_agreement_rate' => null,
                'high_delta_count' => 0,
                'high_delta_percentage' => 0,
                'direction_label' => 'Sin pares',
            ];
        }

        $matchedItems = $comparisons->sum('item_matches_count');
        $comparedItems = $comparisons->sum('item_compared_count');
        $highDeltaCount = $comparisons
            ->filter(fn (array $comparison) => $comparison['absolute_score_delta'] >= self::HIGH_DELTA_THRESHOLD)
            ->count();
        $averageDelta = $comparisons->avg('score_delta');

        return [
            'pair_count' => $pairCount,
            'average_ai_score' => round($comparisons->avg('ai_score'), 2),
            'average_manual_score' => round($comparisons->avg('manual_score'), 2),
            'average_score_delta' => round($averageDelta, 2),
            'average_absolute_delta' => round($comparisons->avg('absolute_score_delta'), 2),
            'item_agreement_rate' => $comparedItems > 0 ? round(($matchedItems / $comparedItems) * 100, 1) : null,
            'high_delta_count' => $highDeltaCount,
            'high_delta_percentage' => round(($highDeltaCount / $pairCount) * 100, 1),
            'direction_label' => $this->directionLabel($averageDelta),
        ];
    }

    public function recentPairs(array $filters, User $user, int $limit = 10): array
    {
        return $this->comparisons($filters, $user)
            ->sortByDesc('manual_created_at')
            ->take($limit)
            ->values()
            ->all();
    }

    public function compareEvaluations(Evaluation $aiEvaluation, Evaluation $manualEvaluation): array
    {
        $aiEvaluation->loadMissing(['agent', 'campaign', 'items.subAttribute']);
        $manualEvaluation->loadMissing(['agent', 'campaign', 'items.subAttribute']);

        $aiScore = (float) $aiEvaluation->percentage_score;
        $manualScore = (float) $manualEvaluation->percentage_score;
        $scoreDelta = round($manualScore - $aiScore, 2);

        $criteria = $this->compareItems($aiEvaluation, $manualEvaluation);
        $itemComparedCount = $criteria->where('comparable', true)->count();
        $itemMatchesCount = $criteria
            ->where('comparable', true)
            ->where('matches', true)
            ->count();

        return [
            'interaction_id' => $manualEvaluation->interaction_id,
            'ai_evaluation_id' => $aiEvaluation->id,
            'manual_evaluation_id' => $manualEvaluation->id,
            'campaign' => $manualEvaluation->campaign?->name ?? $aiEvaluation->campaign?->name ?? 'N/A',
            'agent' => $manualEvaluation->agent?->name ?? $aiEvaluation->agent?->name ?? 'N/A',
            'ai_score' => round($aiScore, 2),
            'manual_score' => round($manualScore, 2),
            'score_delta' => $scoreDelta,
            'absolute_score_delta' => abs($scoreDelta),
            'item_compared_count' => $itemComparedCount,
            'item_matches_count' => $itemMatchesCount,
            'item_agreement_rate' => $itemComparedCount > 0
                ? round(($itemMatchesCount / $itemComparedCount) * 100, 1)
                : null,
            'criteria' => $criteria->values()->all(),
            'manual_created_at' => $manualEvaluation->created_at,
            'ai_created_at' => $aiEvaluation->created_at,
        ];
    }

    private function comparisons(array $filters, User $user): Collection
    {
        return $this->pairedManualEvaluationQuery($filters, $user)
            ->get()
            ->map(function (Evaluation $manualEvaluation) {
                $aiEvaluation = $manualEvaluation->interaction?->evaluations
                    ->firstWhere('type', 'ai');

                if (! $aiEvaluation) {
                    return null;
                }

                return $this->compareEvaluations($aiEvaluation, $manualEvaluation);
            })
            ->filter()
            ->values();
    }

    private function pairedManualEvaluationQuery(array $filters, User $user): Builder
    {
        $query = Evaluation::query()
            ->forUser($user)
            ->where('type', 'manual')
            ->whereHas('interaction.evaluations', function (Builder $query) {
                $query->where('type', 'ai');
            })
            ->with([
                'agent',
                'campaign',
                'items.subAttribute',
                'interaction.evaluations.items.subAttribute',
                'interaction.evaluations.agent',
                'interaction.evaluations.campaign',
            ]);

        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $query->whereBetween('evaluations.created_at', [
                $filters['start_date'].' 00:00:00',
                $filters['end_date'].' 23:59:59',
            ]);
        }

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        return $query;
    }

    private function compareItems(Evaluation $aiEvaluation, Evaluation $manualEvaluation): Collection
    {
        $aiItems = $aiEvaluation->items->keyBy('subattribute_id');
        $manualItems = $manualEvaluation->items->keyBy('subattribute_id');

        return $aiItems->keys()
            ->merge($manualItems->keys())
            ->unique()
            ->sort()
            ->map(function (int $subAttributeId) use ($aiItems, $manualItems) {
                $aiItem = $aiItems->get($subAttributeId);
                $manualItem = $manualItems->get($subAttributeId);
                $comparable = $aiItem !== null && $manualItem !== null;

                return [
                    'subattribute_id' => $subAttributeId,
                    'criterion' => $manualItem?->subAttribute?->name
                        ?? $aiItem?->subAttribute?->name
                        ?? 'Criterio '.$subAttributeId,
                    'ai_status' => $aiItem?->status,
                    'manual_status' => $manualItem?->status,
                    'comparable' => $comparable,
                    'matches' => $comparable && $aiItem->status === $manualItem->status,
                ];
            });
    }

    private function directionLabel(float $averageDelta): string
    {
        if (abs($averageDelta) < 1) {
            return 'Alineada';
        }

        return $averageDelta > 0
            ? 'IA más estricta'
            : 'IA más permisiva';
    }
}
