<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Services\EvaluationCalibrationService;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function evaluations(Request $request)
    {
        if (! auth()->user()->can('export_evaluations') && ! auth()->user()->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator', 'qa_monitor', 'supervisor', 'manager'])) {
            abort(403);
        }

        $query = Evaluation::with([
            'campaign',
            'agent',
            'evaluator',
            'interaction' => function ($query) {
                $query->select('id', 'channel', 'direction', 'contact_reason', 'outcome', 'product_name', 'audio_duration', 'occurred_at');
            },
        ])
            ->forUser(auth()->user())
            ->when($request->filled('campaign_id'), fn ($query) => $query->where('campaign_id', $request->integer('campaign_id')))
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
            ->latest();

        return $this->csv('evaluations.csv', [
            'id',
            'created_at',
            'campaign',
            'agent',
            'type',
            'evaluator',
            'score',
            'status',
            'channel',
            'direction',
            'contact_reason',
            'duration_seconds',
            'product',
            'outcome',
            'agent_viewed',
        ], function ($handle) use ($query) {
            $query->chunk(200, function ($evaluations) use ($handle) {
                foreach ($evaluations as $evaluation) {
                    $interaction = $evaluation->interaction;
                    $this->putCsv($handle, [
                        $evaluation->id,
                        $evaluation->created_at?->toDateTimeString(),
                        $evaluation->campaign?->name,
                        $evaluation->agent?->name,
                        $evaluation->type,
                        $evaluation->evaluator?->name,
                        $evaluation->percentage_score,
                        Evaluation::statusLabel($evaluation->status),
                        $interaction?->channel,
                        $interaction?->direction,
                        $interaction?->contact_reason,
                        $interaction?->audio_duration,
                        $interaction?->product_name,
                        $interaction?->outcome,
                        $evaluation->agent_viewed_at?->toDateTimeString() ?? 'No',
                    ]);
                }
            });
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
}
