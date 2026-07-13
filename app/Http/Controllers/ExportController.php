<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\QualityAttribute;
use App\Models\QualitySubAttribute;
use App\Models\User;
use App\Services\EvaluationCalibrationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportController extends Controller
{
    public function evaluations(Request $request)
    {
        if (! auth()->user()->can('export_evaluations') && ! auth()->user()->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator', 'qa_monitor', 'supervisor', 'manager'])) {
            abort(403);
        }

        $query = $this->evaluationExportQuery($request)->latest();
        $evaluationIds = (clone $query)->pluck('evaluations.id');
        $categoryColumns = $this->evaluationCategoryColumns($evaluationIds);
        $itemColumns = $this->evaluationItemColumns($evaluationIds);
        $headers = array_merge($this->evaluationExportHeaders($categoryColumns), $itemColumns->pluck('header')->all());

        return $this->xlsx($this->evaluationExportFilename($request), $headers, function (Worksheet $sheet, int $startRow) use ($query, $categoryColumns, $itemColumns) {
            $nextRow = $startRow;

            $query->chunk(100, function ($evaluations) use ($sheet, $categoryColumns, $itemColumns, &$nextRow) {
                foreach ($evaluations as $evaluation) {
                    $this->putSpreadsheetRow($sheet, $nextRow, $this->evaluationExportRow($evaluation, $categoryColumns, $itemColumns));
                    $nextRow++;
                }
            });

            return $nextRow;
        });
    }

    public function calibration(Request $request, EvaluationCalibrationService $calibrationService)
    {
        if (! auth()->user()->can('export_calibration') && ! auth()->user()->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator', 'qa_monitor', 'manager'])) {
            abort(403);
        }

        $filters = [
            'start_date' => $request->input('start_date', now()->subDays(30)->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
            'campaign_id' => $request->input('campaign_id'),
        ];
        $pairs = $calibrationService->recentPairs($filters, auth()->user(), 1000);

        return $this->csv('calibration.csv', [
            'interaction_id',
            'campaign',
            'agent',
            'ai_score',
            'manual_score',
            'delta_pp',
            'criteria_agreement',
        ], function ($handle) use ($pairs) {
            foreach ($pairs as $pair) {
                $this->putCsv($handle, [
                    $pair['interaction_id'],
                    $pair['campaign'],
                    $pair['agent'],
                    $pair['ai_score'],
                    $pair['manual_score'],
                    $pair['score_delta'],
                    $pair['item_agreement_rate'],
                ]);
            }
        });
    }

    public function audit(Evaluation $evaluation)
    {
        $this->authorize('view', $evaluation);
        if (! auth()->user()->can('export_evaluation_audit') && ! auth()->user()->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator', 'qa_monitor'])) {
            abort(403);
        }

        $evaluation->load('auditEvents.actor');

        return $this->csv("evaluation-{$evaluation->id}-audit.csv", [
            'occurred_at',
            'event',
            'actor',
            'from_status',
            'to_status',
        ], function ($handle) use ($evaluation) {
            foreach ($evaluation->auditEvents->sortBy('occurred_at') as $event) {
                $this->putCsv($handle, [
                    $event->occurred_at?->toDateTimeString(),
                    $event->event,
                    $event->actor?->name ?? 'Sistema',
                    $event->from_status,
                    $event->to_status,
                ]);
            }
        });
    }

    private function csv(string $filename, array $headers, callable $writer)
    {
        return response()->streamDownload(function () use ($headers, $writer) {
            $handle = fopen('php://output', 'w');
            $this->putCsv($handle, $headers);
            $writer($handle);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function xlsx(string $filename, array $headers, callable $writer)
    {
        return response()->streamDownload(function () use ($headers, $writer) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Evaluaciones');

            $this->putSpreadsheetRow($sheet, 1, $headers);
            $nextRow = (int) ($writer($sheet, 2) ?? 2);
            $this->styleWorksheet($sheet, $headers, max(1, $nextRow - 1));

            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function putCsv($handle, array $row): void
    {
        fputcsv($handle, array_map(fn ($value) => $this->safeCsvValue($value), $row));
    }

    private function safeCsvValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@'], true)
            ? "'{$value}"
            : $value;
    }

    private function evaluationExportQuery(Request $request): Builder
    {
        $query = Evaluation::with([
            'campaign.parent',
            'agent',
            'evaluator',
            'reviewer',
            'reviewClaimer',
            'publisher',
            'closer',
            'reopener',
            'formVersion.form',
            'items.subAttribute.attribute',
            'interaction.campaign.parent',
            'interaction.agent',
            'interaction.supervisor',
            'interaction.uploadedBy',
        ])->forUser(auth()->user())->finalForReporting();

        $this->applyEvaluationFilters($query, $request);

        return $query;
    }

    private function applyEvaluationFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('campaign_id'), fn ($query) => $query->whereIn('campaign_id', Campaign::idsForFilter($request->integer('campaign_id'))))
            ->when(! $request->filled('campaign_id') && $request->filled('parent_campaign_id'), fn ($query) => $query->whereIn('campaign_id', Campaign::idsForFilter($request->integer('parent_campaign_id'))))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('start_date'), fn ($query) => $query->whereDate('evaluations.created_at', '>=', $request->date('start_date')->format('Y-m-d')))
            ->when($request->filled('end_date'), fn ($query) => $query->whereDate('evaluations.created_at', '<=', $request->date('end_date')->format('Y-m-d')))
            ->when($request->filled('score_band'), function ($query) use ($request) {
                match ($request->string('score_band')->toString()) {
                    'excellent' => $query->where('percentage_score', '>=', 90),
                    'good' => $query->whereBetween('percentage_score', [80, 89.99]),
                    'watch' => $query->whereBetween('percentage_score', [70, 79.99]),
                    'critical' => $query->where('percentage_score', '<', 70)->whereNotNull('percentage_score'),
                    'unscored' => $query->whereNull('percentage_score'),
                    default => null,
                };
            })
            ->when($request->filled('q'), fn ($query) => $query->searchIndex($request->string('q')->toString()));
    }

    private function evaluationCategoryColumns(Collection $evaluationIds): Collection
    {
        if ($evaluationIds->isEmpty()) {
            return collect();
        }

        $attributeIds = EvaluationItem::query()
            ->whereIn('evaluation_id', $evaluationIds)
            ->join('quality_subattributes', 'evaluation_items.subattribute_id', '=', 'quality_subattributes.id')
            ->distinct()
            ->pluck('quality_subattributes.attribute_id');

        if ($attributeIds->isEmpty()) {
            return collect();
        }

        return QualityAttribute::query()
            ->whereIn('id', $attributeIds)
            ->get()
            ->sortBy(fn (QualityAttribute $attribute) => sprintf(
                '%010d-%010d-%010d-%s',
                (int) ($attribute->form_version_id ?? 0),
                (int) ($attribute->sort_order ?? 0),
                (int) $attribute->id,
                mb_strtolower((string) $attribute->name)
            ))
            ->reduce(function (Collection $columns, QualityAttribute $attribute) {
                $header = $this->categoryColumnHeader($attribute);
                $key = mb_strtolower(trim(preg_replace('/\s+/', ' ', $header)));
                $existingIndex = $columns->search(fn (array $column) => $column['key'] === $key);

                if ($existingIndex === false) {
                    return $columns->push([
                        'key' => $key,
                        'header' => $header,
                        'attribute_ids' => [$attribute->id],
                    ]);
                }

                $column = $columns->get($existingIndex);
                $column['attribute_ids'][] = $attribute->id;
                $columns->put($existingIndex, $column);

                return $columns;
            }, collect())
            ->values();
    }

    private function categoryColumnHeader(QualityAttribute $attribute): string
    {
        $name = trim((string) $attribute->name);
        $slug = $name !== '' ? Str::slug($name, '_') : "categoria_{$attribute->id}";

        return "categoria_{$slug}_porcentaje";
    }

    private function evaluationItemColumns(Collection $evaluationIds): Collection
    {
        if ($evaluationIds->isEmpty()) {
            return collect();
        }

        $subAttributeIds = EvaluationItem::query()
            ->whereIn('evaluation_id', $evaluationIds)
            ->distinct()
            ->pluck('subattribute_id');

        if ($subAttributeIds->isEmpty()) {
            return collect();
        }

        return QualitySubAttribute::with('attribute')
            ->whereIn('id', $subAttributeIds)
            ->get()
            ->sortBy(fn (QualitySubAttribute $subAttribute) => sprintf(
                '%010d-%010d-%010d-%010d-%s-%s',
                (int) ($subAttribute->attribute?->form_version_id ?? 0),
                (int) ($subAttribute->attribute?->sort_order ?? 0),
                (int) ($subAttribute->attribute_id ?? 0),
                (int) ($subAttribute->sort_order ?? 0),
                mb_strtolower((string) ($subAttribute->attribute?->name ?? '')),
                mb_strtolower((string) $subAttribute->name)
            ))
            ->reduce(function (Collection $columns, QualitySubAttribute $subAttribute) {
                $header = $this->itemColumnHeader($subAttribute);
                $key = mb_strtolower(trim(preg_replace('/\s+/', ' ', $header)));
                $existingIndex = $columns->search(fn (array $column) => $column['key'] === $key);

                if ($existingIndex === false) {
                    return $columns->push([
                        'key' => $key,
                        'header' => $header,
                        'subattribute_ids' => [$subAttribute->id],
                    ]);
                }

                $column = $columns->get($existingIndex);
                $column['subattribute_ids'][] = $subAttribute->id;
                $columns->put($existingIndex, $column);

                return $columns;
            }, collect())
            ->values();
    }

    private function itemColumnHeader(QualitySubAttribute $subAttribute): string
    {
        $name = trim((string) $subAttribute->name);
        $attribute = trim((string) ($subAttribute->attribute?->name ?? ''));

        return $name !== ''
            ? $name
            : ($attribute !== '' ? $attribute : "item_{$subAttribute->id}");
    }

    private function evaluationExportHeaders(Collection $categoryColumns): array
    {
        $headers = [
            'evaluacion_id',
            'interaccion_id',
            'tipo_evaluacion',
            'estado_evaluacion',
            'puntaje_total',
            'puntaje_maximo',
            'puntaje_porcentaje',
            'es_gold',
            'fecha_evaluacion',
            'hora_evaluacion',
            'campana',
            'subcampana',
            'campana_completa',
            'ficha',
            'ficha_version',
            'monitor_evaluador',
            'monitor_revisor',
            'monitor_publicador',
            'agente',
            'agente_usuario',
            'agente_email',
            'supervisor',
            'supervisor_usuario',
            'subido_por',
            'fecha_carga',
            'hora_carga',
            'dia_carga',
            'fecha_llamada',
            'hora_llamada',
            'dia_llamada',
            'call_sn',
            'external_id',
            'batch_id',
            'archivo_audio',
            'link_audio',
            'ruta_audio_servidor',
            'canal',
            'direccion',
            'motivo_contacto',
            'resultado',
            'cliente_referencia',
            'cola',
            'producto',
            'prioridad',
            'estado_interaccion',
            'estado_transcripcion',
            'proveedor_transcripcion',
            'modelo_transcripcion',
            'inicio_transcripcion',
            'fin_transcripcion',
            'tmo_segundos',
            'tmo',
            'transcripcion',
            'proveedor_ia',
            'modelo_ia',
            'procesado_ia',
            'version_prompt_ia',
            'hash_prompt_ia',
            'resumen_ia',
            'feedback_desempeno',
            'feedback_producto',
            'feedback_emocional',
            'fortalezas',
            'oportunidades_mejora',
            'sentimiento_general',
            'sentimiento_score',
            'sentimiento_confianza',
            'sentimiento_resumen',
            'sentimiento_agente',
            'sentimiento_cliente',
            'tono_agente',
            'tono_cliente',
            'ritmo_agente',
            'ritmo_cliente',
            'energia_agente',
            'energia_cliente',
            'satisfaccion_cliente',
            'ritmo_general',
            'velocidad_agente_wpm',
            'velocidad_cliente_wpm',
            'claridad_voz',
            'interrupciones',
            'interrupciones_agente',
            'interrupciones_cliente',
            'overlaps',
            'volumen_promedio',
            'ruido',
            'ratio_habla_agente',
            'ratio_habla_cliente',
            'balance_conversacion',
            'nota_balance_conversacion',
            'pausas_largas',
            'silencio_ratio',
            'silencio_total_segundos',
            'silencio_total',
            'silencio_mayor_segundos',
            'silencio_mayor',
            'silencio_detectado_por',
            'segmentos_silencio',
            'punto_inflexion_emocional',
            'notas_acusticas',
            'empatia',
            'escucha_activa',
            'manejo_objeciones',
            'claridad_resolucion',
            'control_script',
            'calidad_cierre',
            'riesgo_experiencia_cliente',
            'riesgo_cumplimiento',
            'riesgo_sentimiento',
            'recuperacion_emocional',
            'control_agente',
            'causa_frustracion',
            'cliente_queda_sin_solucion',
            'claridad',
            'resumen_senales_calidad',
            'alertas_supervisor',
            'momentos_criticos',
            'recomendaciones_coaching',
            'segmentos_sentimiento',
            'origen_carga',
            'ruta_origen_importacion',
            'tags_carga',
            'version_analisis_audio',
            'inicio_scoring',
            'fin_scoring',
            'visto_por_agente',
            'visible_para_agente',
            'finalizado_at',
            'revisado_at',
            'notas_revision',
            'motivo_cierre',
        ];

        array_splice($headers, 7, 0, $categoryColumns->pluck('header')->all());

        return $headers;
    }

    private function evaluationExportRow(Evaluation $evaluation, Collection $categoryColumns, Collection $itemColumns): array
    {
        $interaction = $evaluation->interaction;
        $campaign = $evaluation->campaign ?? $interaction?->campaign;
        [$campaignName, $subcampaignName, $campaignDisplayName] = $this->campaignParts($campaign);
        $metadata = is_array($interaction?->metadata) ? $interaction->metadata : [];
        $sentiment = is_array($metadata['sentiment'] ?? null) ? $metadata['sentiment'] : [];
        $acoustic = is_array($metadata['acoustic_analysis'] ?? null) ? $metadata['acoustic_analysis'] : [];
        $qualitySignals = is_array($metadata['quality_signals'] ?? null) ? $metadata['quality_signals'] : [];
        $feedback = collect($evaluation->structuredAiFeedbackForPrompt());
        $itemsBySubAttribute = $evaluation->items->keyBy('subattribute_id');

        $row = [
            $evaluation->id,
            $interaction?->id,
            $evaluation->type,
            Evaluation::statusLabel($evaluation->status),
            $evaluation->total_score,
            $evaluation->max_possible_score,
            $evaluation->percentage_score,
            ...$this->categoryColumnValues($evaluation, $categoryColumns),
            $evaluation->is_gold ? '1' : '0',
            $evaluation->created_at?->toDateString(),
            $evaluation->created_at?->format('H:i:s'),
            $campaignName,
            $subcampaignName,
            $campaignDisplayName,
            $evaluation->formVersion?->form?->name,
            $evaluation->formVersion?->version_number,
            $this->userName($evaluation->evaluator),
            $this->userName($evaluation->reviewer),
            $this->userName($evaluation->publisher),
            $this->userName($evaluation->agent ?? $interaction?->agent),
            ($evaluation->agent ?? $interaction?->agent)?->username,
            ($evaluation->agent ?? $interaction?->agent)?->email,
            $this->userName($interaction?->supervisor),
            $interaction?->supervisor?->username,
            $this->userName($interaction?->uploadedBy),
            $interaction?->uploaded_at?->toDateString(),
            $interaction?->uploaded_at?->format('H:i:s'),
            $interaction?->uploaded_at?->translatedFormat('l'),
            $interaction?->occurred_at?->toDateString(),
            $interaction?->occurred_at?->format('H:i:s'),
            $interaction?->occurred_at?->translatedFormat('l'),
            $interaction?->call_sn,
            $interaction?->external_id,
            $interaction?->batch_id,
            $interaction?->file_name,
            $this->audioUrl($interaction),
            $interaction?->file_path,
            $interaction?->channel,
            $interaction?->direction,
            $interaction?->contact_reason,
            $interaction?->outcome,
            $interaction?->customer_reference,
            $interaction?->queue_name,
            $interaction?->product_name,
            $interaction?->priority,
            $interaction?->status,
            $interaction?->transcription_status,
            $metadata['transcription_provider'] ?? null,
            $metadata['transcription_model'] ?? null,
            $metadata['transcription_started_at'] ?? null,
            $metadata['transcription_completed_at'] ?? null,
            $interaction?->audio_duration,
            $this->formatSeconds($interaction?->audio_duration),
            $this->transcriptTextForExport($interaction?->transcript_text),
            $evaluation->ai_provider,
            $evaluation->ai_model,
            $evaluation->ai_processed_at?->toDateTimeString(),
            $evaluation->ai_prompt_version,
            $evaluation->ai_prompt_hash,
            $evaluation->ai_summary,
            $feedback->get('performanceSummary'),
            $feedback->get('productKnowledge'),
            $feedback->get('emotionalHandlingAndEmpathy'),
            $feedback->get('strengths'),
            $feedback->get('improvementOpportunities'),
            data_get($sentiment, 'overall'),
            data_get($sentiment, 'overall_score') ?? data_get($sentiment, 'score'),
            data_get($sentiment, 'confidence'),
            data_get($sentiment, 'summary'),
            data_get($sentiment, 'agent.sentiment'),
            data_get($sentiment, 'client.sentiment'),
            data_get($sentiment, 'agent.tone'),
            data_get($sentiment, 'client.tone'),
            data_get($sentiment, 'agent.pace'),
            data_get($sentiment, 'client.pace'),
            data_get($sentiment, 'agent.energy'),
            data_get($sentiment, 'client.energy'),
            data_get($sentiment, 'client.satisfaction'),
            data_get($acoustic, 'overall_pace'),
            data_get($acoustic, 'agent_speech_rate_wpm'),
            data_get($acoustic, 'client_speech_rate_wpm'),
            data_get($acoustic, 'clarity'),
            data_get($acoustic, 'interruptions'),
            data_get($acoustic, 'agent_interruptions'),
            data_get($acoustic, 'client_interruptions'),
            data_get($acoustic, 'overlap_count'),
            data_get($acoustic, 'average_volume'),
            data_get($acoustic, 'noise_level'),
            data_get($acoustic, 'agent_talk_ratio'),
            data_get($acoustic, 'client_talk_ratio'),
            data_get($acoustic, 'talk_balance'),
            data_get($acoustic, 'talk_balance_note'),
            data_get($acoustic, 'long_pauses'),
            data_get($acoustic, 'silence_ratio'),
            data_get($acoustic, 'dead_air_total_seconds'),
            data_get($acoustic, 'dead_air_total_label'),
            data_get($acoustic, 'dead_air_longest_seconds'),
            data_get($acoustic, 'dead_air_longest_label'),
            data_get($acoustic, 'dead_air_detected_by'),
            $this->formatSilenceSegments(data_get($acoustic, 'dead_air_segments')),
            $this->formatAssociativeValue(data_get($acoustic, 'emotional_turning_point')),
            data_get($acoustic, 'notes'),
            data_get($qualitySignals, 'empathy'),
            data_get($qualitySignals, 'active_listening'),
            data_get($qualitySignals, 'objection_handling'),
            data_get($qualitySignals, 'resolution_clarity'),
            data_get($qualitySignals, 'script_control'),
            data_get($qualitySignals, 'closing_quality'),
            data_get($qualitySignals, 'customer_experience_risk'),
            data_get($qualitySignals, 'compliance_risk'),
            data_get($qualitySignals, 'sentiment_risk'),
            data_get($qualitySignals, 'emotional_recovery'),
            data_get($qualitySignals, 'agent_control'),
            data_get($qualitySignals, 'frustration_cause'),
            data_get($qualitySignals, 'customer_left_unresolved'),
            data_get($qualitySignals, 'clarity'),
            data_get($qualitySignals, 'summary'),
            $this->formatSupervisorAlerts(data_get($qualitySignals, 'supervisor_alerts')),
            $this->formatCriticalMoments(data_get($qualitySignals, 'critical_moments')),
            $this->formatCoachingRecommendations(data_get($qualitySignals, 'coaching_recommendations')),
            $this->formatSentimentSegments($metadata['sentiment_segments'] ?? $metadata['emotion_segments'] ?? null),
            data_get($metadata, 'upload.origin'),
            data_get($metadata, 'bulk_import.source_relative_path'),
            $this->formatListValue(data_get($metadata, 'upload.tags')),
            $metadata['audio_analysis_version'] ?? null,
            $metadata['scoring_started_at'] ?? null,
            $metadata['scoring_completed_at'] ?? null,
            $evaluation->agent_viewed_at?->toDateTimeString(),
            $evaluation->visible_to_agent_at?->toDateTimeString(),
            $evaluation->finalized_at?->toDateTimeString(),
            $evaluation->reviewed_at?->toDateTimeString(),
            $evaluation->review_notes,
            $evaluation->closure_reason,
        ];

        foreach ($itemColumns as $column) {
            $row[] = $this->itemColumnValue($itemsBySubAttribute, $column['subattribute_ids']);
        }

        return $row;
    }

    private function categoryColumnValues(Evaluation $evaluation, Collection $categoryColumns): array
    {
        if ($categoryColumns->isEmpty()) {
            return [];
        }

        $items = $evaluation->items;
        $hasCriticalFailure = $items->contains(
            fn (EvaluationItem $item) => (bool) ($item->subAttribute?->is_critical)
                && $item->status === 'non_compliant'
        );

        $totalPossible = $items
            ->filter(fn (EvaluationItem $item) => ! (bool) ($item->subAttribute?->is_critical))
            ->sum(fn (EvaluationItem $item) => $this->effectiveWeightForItem($item));

        return $categoryColumns
            ->map(function (array $column) use ($items, $hasCriticalFailure, $totalPossible) {
                $categoryItems = $items->filter(function (EvaluationItem $item) use ($column) {
                    $attributeId = $item->subAttribute?->attribute_id;

                    return $attributeId !== null
                        && in_array($attributeId, $column['attribute_ids'], true)
                        && ! (bool) ($item->subAttribute?->is_critical);
                });

                if ($categoryItems->isEmpty()) {
                    return '';
                }

                if ($hasCriticalFailure || $totalPossible <= 0) {
                    return 0.0;
                }

                $categoryScore = $categoryItems->sum(fn (EvaluationItem $item) => (float) $item->weighted_score);

                return round(($categoryScore / $totalPossible) * 100, 2);
            })
            ->all();
    }

    private function effectiveWeightForItem(EvaluationItem $item): float
    {
        $subAttribute = $item->subAttribute;
        $attribute = $subAttribute?->attribute;

        if (! $subAttribute || ! $attribute) {
            return 0.0;
        }

        return ((float) $attribute->weight * (float) $subAttribute->weight_percent) / 100;
    }

    private function campaignParts(?Campaign $campaign): array
    {
        if (! $campaign) {
            return [null, null, null];
        }

        if ($campaign->parent) {
            return [$campaign->parent->name, $campaign->name, $campaign->displayName()];
        }

        return [$campaign->name, null, $campaign->name];
    }

    private function userName(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return trim(collect([$user->name, $user->paternal_surname, $user->maternal_surname])
            ->filter()
            ->implode(' '));
    }

    private function evaluationExportFilename(Request $request): string
    {
        $parts = ['evaluaciones'];

        if ($request->filled('campaign_id')) {
            $campaign = Campaign::with('parent')->find($request->integer('campaign_id'));

            if ($campaign?->parent) {
                $parts[] = $campaign->parent->name;
                $parts[] = $campaign->name;
            } elseif ($campaign) {
                $parts[] = $campaign->name;
            }
        } elseif ($request->filled('parent_campaign_id')) {
            $campaign = Campaign::find($request->integer('parent_campaign_id'));

            if ($campaign) {
                $parts[] = $campaign->name;
            }
        }

        if (count($parts) === 1) {
            $parts[] = 'todas-campanas';
        }

        $parts[] = now('America/Lima')->format('Ymd-His');

        return Str::slug(implode('-', $parts)).'.xlsx';
    }

    private function audioUrl($interaction): ?string
    {
        return $interaction?->isAudio() ? route('transcripts.audio', $interaction) : null;
    }

    private function formatSeconds(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return $hours > 0
            ? sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds)
            : sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }

    private function transcriptTextForExport(?string $transcript): ?string
    {
        $transcript = trim((string) $transcript);

        if ($transcript === '') {
            return null;
        }

        if (! str_starts_with($transcript, '{')) {
            return $transcript;
        }

        $decoded = json_decode($transcript, true);

        return is_array($decoded) && is_string($decoded['transcript'] ?? null)
            ? $decoded['transcript']
            : $transcript;
    }

    private function itemColumnValue(Collection $itemsBySubAttribute, array $subAttributeIds): string
    {
        foreach ($subAttributeIds as $subAttributeId) {
            $item = $itemsBySubAttribute->get($subAttributeId);

            if ($item) {
                return $this->itemStatusValue($item->status);
            }
        }

        return '';
    }

    private function itemStatusValue(?string $status): string
    {
        return match ($status) {
            'compliant' => '1',
            'non_compliant' => '0',
            'not_applicable', 'not_found' => 'NA',
            default => '',
        };
    }

    private function jsonValue(mixed $value): string
    {
        if ($value === null || $value === [] || $value === '') {
            return '';
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function formatSupervisorAlerts(mixed $alerts): string
    {
        return collect(is_array($alerts) ? $alerts : [])
            ->filter(fn ($alert) => is_array($alert))
            ->map(function (array $alert) {
                return trim(collect([
                    data_get($alert, 'label') ? '['.data_get($alert, 'label').']' : null,
                    data_get($alert, 'level'),
                    data_get($alert, 'message'),
                ])->filter()->implode(' - '));
            })
            ->filter()
            ->implode(' | ');
    }

    private function formatCriticalMoments(mixed $moments): string
    {
        return collect(is_array($moments) ? $moments : [])
            ->filter(fn ($moment) => is_array($moment))
            ->map(function (array $moment) {
                return trim(collect([
                    data_get($moment, 'label') ? '['.data_get($moment, 'label').']' : null,
                    data_get($moment, 'type'),
                    data_get($moment, 'title'),
                    data_get($moment, 'evidence'),
                    data_get($moment, 'feedback'),
                ])->filter()->implode(' - '));
            })
            ->filter()
            ->implode(' | ');
    }

    private function formatCoachingRecommendations(mixed $recommendations): string
    {
        return collect(is_array($recommendations) ? $recommendations : [])
            ->filter(fn ($recommendation) => is_array($recommendation))
            ->map(function (array $recommendation) {
                return trim(collect([
                    data_get($recommendation, 'priority'),
                    data_get($recommendation, 'skill'),
                    data_get($recommendation, 'recommendation'),
                    data_get($recommendation, 'example') ? 'Ejemplo: '.data_get($recommendation, 'example') : null,
                ])->filter()->implode(' - '));
            })
            ->filter()
            ->implode(' | ');
    }

    private function formatSentimentSegments(mixed $segments): string
    {
        return collect(is_array($segments) ? $segments : [])
            ->filter(fn ($segment) => is_array($segment))
            ->take(20)
            ->map(function (array $segment) {
                return trim(collect([
                    data_get($segment, 'start') !== null ? '['.$this->formatSeconds((int) data_get($segment, 'start')).']' : null,
                    data_get($segment, 'speaker'),
                    data_get($segment, 'sentiment'),
                    data_get($segment, 'emotion'),
                    data_get($segment, 'evidence'),
                ])->filter()->implode(' - '));
            })
            ->filter()
            ->implode(' | ');
    }

    private function formatSilenceSegments(mixed $segments): string
    {
        return collect(is_array($segments) ? $segments : [])
            ->filter(fn ($segment) => is_array($segment))
            ->take(20)
            ->map(function (array $segment) {
                $range = collect([
                    data_get($segment, 'start_label') ?? data_get($segment, 'start'),
                    data_get($segment, 'end_label') ?? data_get($segment, 'end'),
                ])->filter()->implode('-');

                return trim(collect([
                    $range !== '' ? $range : null,
                    data_get($segment, 'duration') !== null ? data_get($segment, 'duration').'s' : null,
                ])->filter()->implode(' '));
            })
            ->filter()
            ->implode(' | ');
    }

    private function formatListValue(mixed $value): string
    {
        if (! is_array($value)) {
            return $this->formatScalarValue($value);
        }

        return collect($value)
            ->map(fn ($item) => is_array($item) ? $this->formatAssociativeValue($item) : $this->formatScalarValue($item))
            ->filter()
            ->implode(' | ');
    }

    private function formatAssociativeValue(mixed $value): string
    {
        if (! is_array($value)) {
            return $this->formatScalarValue($value);
        }

        if (array_is_list($value)) {
            return $this->formatListValue($value);
        }

        return collect($value)
            ->map(function ($item, $key) {
                $text = is_array($item) ? $this->formatAssociativeValue($item) : $this->formatScalarValue($item);

                return $text === '' ? null : $this->humanKey((string) $key).': '.$text;
            })
            ->filter()
            ->implode('; ');
    }

    private function formatScalarValue(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '',
            is_bool($value) => $value ? 'Si' : 'No',
            default => (string) $value,
        };
    }

    private function humanKey(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }

    private function putSpreadsheetRow(Worksheet $sheet, int $rowNumber, array $row): void
    {
        foreach (array_values($row) as $index => $value) {
            $coordinate = Coordinate::stringFromColumnIndex($index + 1).$rowNumber;

            if (is_int($value) || is_float($value)) {
                $sheet->setCellValue($coordinate, $value);

                continue;
            }

            if (is_bool($value)) {
                $sheet->setCellValue($coordinate, $value ? 1 : 0);

                continue;
            }

            $sheet->setCellValueExplicit(
                $coordinate,
                $this->safeSpreadsheetValue($value),
                DataType::TYPE_STRING
            );
        }
    }

    private function safeSpreadsheetValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = $this->formatAssociativeValue(is_object($value) ? (array) $value : $value);
        }

        $value = $value === null ? '' : (string) $value;

        if ($value === '') {
            return '';
        }

        $value = $this->trimForExcelCell($value);

        return in_array($value[0], ['=', '+', '-', '@'], true)
            ? "'{$value}"
            : $value;
    }

    private function trimForExcelCell(string $value): string
    {
        $maxLength = 32767;

        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        $suffix = ' [truncated]';

        return mb_substr($value, 0, $maxLength - mb_strlen($suffix)).$suffix;
    }

    private function styleWorksheet(Worksheet $sheet, array $headers, int $lastRow): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setVertical('top');

        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $width = in_array($header, [
                'transcripcion',
                'resumen_ia',
                'feedback_desempeno',
                'feedback_producto',
                'feedback_emocional',
                'fortalezas',
                'oportunidades_mejora',
                'alertas_supervisor',
                'momentos_criticos',
                'recomendaciones_coaching',
                'segmentos_sentimiento',
                'segmentos_silencio',
            ], true) ? 60 : 22;

            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }
}
