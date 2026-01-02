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
        $formVersion = $campaign->activeFormVersion ?? \App\Models\QualityFormVersion::where('is_active', true)->latest()->first();

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
            $itemsData = [];

            foreach ($validated['items'] as $itemData) {
                // Find subattribute to get weight/points
                // Optimización: cargar todos los subatributos antes si es lento
                $subAttribute = \App\Models\QualitySubAttribute::find($itemData['subattribute_id']);
                
                $weight = $subAttribute->weight ?? 1; // Simplificación si no hay peso en DB
                $maxPoints = 100; // Asumiendo base 100 o puntos por item
                // Depende de cómo QualitySubAttribute defina el puntaje.
                // REVISAR: QualitySubAttribute tiene evaluation_criteria? 
                // Por ahora asumiremos que cada item vale lo mismo o usaremos logic simple.
                // Vamos a asumir que el puntaje es binario por ahora: Cumple = 100%, No = 0%.
                
                // MEJOR ENFOQUE: Usar el servicio de cálculo si existe, o hacerlo manual simple.
                // Si la tabla evaluation_items tiene 'score' y 'max_score'.
                
                // NOTA: Para no complicar, asumiremos un cálculo simple basado en pesos si existen, o conteo.
                // Revisando EvaluationItem: score, max_score, weighted_score.
                
                $itemScore = match($itemData['status']) {
                    'compliant' => $subAttribute->weight ?? 10,
                    'non_compliant' => 0,
                    'not_found' => 0, // O N/A?
                };
                
                $itemMaxScore = $subAttribute->weight ?? 10;
                
                $totalScore += $itemScore;
                $totalPossible += $itemMaxScore;
                
                $itemsData[] = [
                    'subattribute_id' => $subAttribute->id,
                    'status' => $itemData['status'],
                    'score' => $itemScore,
                    'max_score' => $itemMaxScore,
                    'weighted_score' => $itemScore, // Simplificado
                    'confidence' => 1.0, // Manual = 100% confidence
                    'ai_notes' => $itemData['notes'] ?? null,
                ];
            }
            
            $percentage = $totalPossible > 0 ? ($totalScore / $totalPossible) * 100 : 0;

            $evaluation = \App\Models\Evaluation::create([
                'interaction_id' => $interaction->id,
                'form_version_id' => $formVersion->id,
                'campaign_id' => $interaction->campaign_id,
                'agent_id' => $interaction->agent_id,
                'type' => 'manual',
                'evaluator_id' => auth()->id(),
                'total_score' => $totalScore,
                'max_possible_score' => $totalPossible,
                'percentage_score' => $percentage,
                'status' => 'visible_to_agent', // Directo a visible? O pending review?
                'finalized_at' => now(),
            ]);

            $evaluation->items()->createMany($itemsData);

            return $evaluation;
        });

        return redirect()->route('evaluations.show', $evaluation)->with('success', 'Evaluación manual creada exitosamente.');
    }
}
