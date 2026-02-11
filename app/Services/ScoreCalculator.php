<?php

namespace App\Services;

use App\Models\Evaluation;

class ScoreCalculator
{
    public function calculateTotal(Evaluation $evaluation): float
    {
        $totalScore = $evaluation->items->sum('weighted_score');
        
        // El puntaje ponderado ya está calculado en cada item
        // Solo sumamos todos los weighted_scores
        
        return round($totalScore, 2);
    }

    public function calculateItemScore($subAttribute, string $status): array
    {
        // Calcular el peso efectivo del subatributo
        $effectiveWeight = ($subAttribute->attribute->weight * $subAttribute->weight_percent) / 100;
        
        // Determinar el score base según el status
        $baseScore = match($status) {
            'compliant' => 1.0,
            'non_compliant' => 0.0,
            'not_found' => 0.0,
            'not_applicable' => 1.0, // N/A no penaliza
            default => 0.0,
        };
        
        // Calcular el weighted score
        $weightedScore = $baseScore * $effectiveWeight;
        
        return [
            'score' => $baseScore,
            'max_score' => 1.0,
            'weighted_score' => round($weightedScore, 2),
        ];
    }
}
