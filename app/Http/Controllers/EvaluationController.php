<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateFeedbackAudioJob;
use App\Jobs\ScoreTranscriptJob;
use App\Models\Campaign;
use App\Models\Evaluation;
use App\Services\EvaluationCalibrationService;
use App\Support\TranscriptConversationParser;
use App\Support\TranscriptAudioTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EvaluationController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Evaluation::with([
            'agent',
            'campaign.parent',
            'interaction' => function ($query) {
                $query->select('id', 'campaign_id', 'agent_id', 'channel', 'direction', 'contact_reason', 'outcome', 'product_name', 'queue_name', 'audio_duration', 'occurred_at', 'source_type');
            },
            'evaluator',
            'reviewer',
            'publisher',
            'dispute',
        ])->forUser($user);

        $this->applyIndexFilters($query, $request);
        $this->applyFinalEvaluationScope($query);

        $summary = $this->indexSummary(clone $query);
        $evaluations = $query->latest()->paginate(20)->withQueryString();
        $campaigns = Campaign::active()->forUser($user)->orderedForSelect()->get();
        $statusOptions = [
            Evaluation::STATUS_PENDING_AI,
            Evaluation::STATUS_AI_PROCESSING,
            Evaluation::STATUS_AI_FAILED,
            Evaluation::STATUS_PENDING_MONITOR_REVIEW,
            Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
            Evaluation::STATUS_PUBLISHED_TO_AGENT,
            Evaluation::STATUS_AGENT_ACCEPTED,
            Evaluation::STATUS_AGENT_DISPUTED,
            Evaluation::STATUS_DISPUTE_RESOLVED,
            Evaluation::STATUS_CLOSED,
        ];
        $typeOptions = [
            'ai' => 'IA',
            'manual' => 'Manual',
        ];

        return view('evaluations.index', compact('evaluations', 'campaigns', 'statusOptions', 'typeOptions', 'summary'));
    }

    private function applyFinalEvaluationScope($query): void
    {
        $query->where(function ($query) {
            $query
                ->where('type', 'manual')
                ->orWhereDoesntHave('interaction.manualEvaluation');
        });
    }

    private function applyIndexFilters($query, Request $request): void
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
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim($request->string('q')->toString());

                $query->where(function ($query) use ($term) {
                    $query
                        ->whereHas('agent', fn ($agentQuery) => $agentQuery->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                        ->orWhereHas('campaign', fn ($campaignQuery) => $campaignQuery
                            ->where('name', 'like', "%{$term}%")
                            ->orWhereHas('parent', fn ($parentQuery) => $parentQuery->where('name', 'like', "%{$term}%")))
                        ->orWhereHas('evaluator', fn ($evaluatorQuery) => $evaluatorQuery->where('name', 'like', "%{$term}%"));
                });
            });
    }

    private function indexSummary($query): array
    {
        $evaluations = $query->get();
        $scored = $evaluations->whereNotNull('percentage_score');

        return [
            'total' => $evaluations->count(),
            'avg_score' => round((float) $scored->avg('percentage_score'), 1),
            'pending_review' => $evaluations->whereIn('status', [
                Evaluation::STATUS_PENDING_MONITOR_REVIEW,
                Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
            ])->count(),
            'disputed' => $evaluations->where('status', Evaluation::STATUS_AGENT_DISPUTED)->count(),
            'critical' => $scored->filter(fn (Evaluation $evaluation) => (float) $evaluation->percentage_score < 70)->count(),
            'closed' => $evaluations->where('status', Evaluation::STATUS_CLOSED)->count(),
        ];
    }

    public function show(
        Evaluation $evaluation,
        EvaluationCalibrationService $calibrationService,
        TranscriptConversationParser $conversationParser,
        TranscriptAudioTimeline $audioTimelineBuilder
    ) {
        $this->authorize('view', $evaluation);

        if ($evaluation->type === 'ai') {
            $manualEvaluation = $evaluation->interaction?->manualEvaluation()->first();

            if ($manualEvaluation) {
                $this->authorize('view', $manualEvaluation);

                return redirect()->route('evaluations.show', $manualEvaluation);
            }
        }

        $evaluation->load([
            'interaction.aiEvaluation.items.subAttribute',
            'interaction.manualEvaluation.items.subAttribute',
            'interaction.campaign.parent',
            'campaign.parent',
            'formVersion.attributes.subAttributes',
            'items.subAttribute.attribute', // Ensure relationships are correct in models
            'agentResponse',
            'dispute.supervisorReviewer',
            'dispute.qaReviewer',
            'dispute.coordinatorReviewer',
            'dispute.resolvedBy',
            'auditEvents.actor',
            'reviewer',
            'reviewClaimer',
            'publisher',
            'evaluator',
            'closer',
            'reopener',
        ]);

        $calibrationComparison = auth()->user()->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator', 'qa_monitor', 'supervisor'])
            ? $calibrationService->compareForEvaluation($evaluation)
            : null;

        // Marcar como vista por el asesor
        if (auth()->user()->hasRole('agent') && $evaluation->isVisibleToAgent() && ! $evaluation->agent_viewed_at) {
            $evaluation->update(['agent_viewed_at' => now()]);
        }

        $feedbackAudioUrl = $evaluation->feedback_audio_status === 'ready' && $evaluation->feedback_audio_path
            ? route('evaluations.feedback-audio', $evaluation)
            : null;

        $interactionAudioUrl = $evaluation->interaction?->isAudio() && auth()->user()->can('view_transcripts')
            ? route('transcripts.audio', $evaluation->interaction)
            : null;

        $audioTimeline = null;
        if ($evaluation->interaction?->isAudio() && $evaluation->interaction->transcript_text) {
            $conversationTurns = $conversationParser->parse($evaluation->interaction->transcript_text);
            $audioTimeline = $audioTimelineBuilder->build(
                $conversationTurns,
                $evaluation->interaction->audio_duration,
                $this->audioMetadata($evaluation->interaction, $conversationParser)
            );
        }

        return view('evaluations.show', compact('evaluation', 'calibrationComparison', 'feedbackAudioUrl', 'interactionAudioUrl', 'audioTimeline'));
    }

    private function audioMetadata(\App\Models\Interaction $interaction, TranscriptConversationParser $conversationParser): array
    {
        $metadata = $interaction->metadata ?? [];

        if (! empty($metadata['sentiment'])) {
            return $metadata;
        }

        $parsed = $conversationParser->extractStructuredPayload($interaction->transcript_text);

        if (is_array($parsed) && isset($parsed['sentiment'])) {
            $metadata['sentiment'] = $parsed['sentiment'];
        }

        if (is_array($parsed) && isset($parsed['sentiment_segments']) && is_array($parsed['sentiment_segments'])) {
            $metadata['sentiment_segments'] = $parsed['sentiment_segments'];
        }

        foreach (['acoustic_analysis', 'quality_signals'] as $key) {
            if (is_array($parsed) && isset($parsed[$key]) && is_array($parsed[$key])) {
                $metadata[$key] = $parsed[$key];
            }
        }

        return $metadata;
    }

    public function publish(Request $request, Evaluation $evaluation)
    {
        $this->authorize('publish', $evaluation);

        $validated = $request->validate([
            'review_notes' => 'nullable|string|max:5000',
        ]);

        if (! $evaluation->canBePublished()) {
            return back()->with('error', 'Esta evaluación no está en un estado publicable.');
        }

        $fromStatus = $evaluation->status;

        $evaluation->update([
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'] ?? null,
            'published_by' => auth()->id(),
            'visible_to_agent_at' => now(),
            'finalized_at' => now(),
            'evaluator_id' => $evaluation->evaluator_id ?: auth()->id(),
        ]);

        $evaluation->recordAuditEvent('published', auth()->user(), [
            'type' => $evaluation->type,
            'percentage_score' => $evaluation->percentage_score,
            'review_notes_present' => filled($validated['review_notes'] ?? null),
        ], $fromStatus, Evaluation::STATUS_PUBLISHED_TO_AGENT);

        if ($evaluation->agent) {
            $evaluation->agent->notify(new \App\Notifications\EvaluationCompleted($evaluation));
        }

        if (config('ai.feedback_tts.enabled')) {
            $evaluation->update(['feedback_audio_status' => 'pending']);
            GenerateFeedbackAudioJob::dispatch($evaluation->id);
        }

        return redirect()->route('evaluations.show', $evaluation)
            ->with('success', 'Evaluación aprobada y publicada al asesor.');
    }

    public function feedbackAudio(Request $request, Evaluation $evaluation)
    {
        $this->authorize('view', $evaluation);

        if ($evaluation->feedback_audio_status !== 'ready' || ! $evaluation->feedback_audio_path) {
            abort(404);
        }

        $diskName = $evaluation->feedback_audio_disk ?: config('ai.feedback_tts.audio_disk', config('filesystems.default'));
        $disk = Storage::disk($diskName);

        if (! $disk->exists($evaluation->feedback_audio_path)) {
            abort(404);
        }

        $mime = 'audio/mpeg';
        $size = (int) $disk->size($evaluation->feedback_audio_path);
        $fileName = 'feedback-evaluacion-'.$evaluation->id.'.mp3';

        if ($size <= 0) {
            return response('', 200, [
                'Content-Type' => $mime,
                'Content-Length' => '0',
                'Accept-Ranges' => 'bytes',
                'Content-Disposition' => 'inline; filename="'.addslashes($fileName).'"',
            ]);
        }

        $range = $this->audioRange($request->headers->get('Range'), $size);

        if (! $range['satisfiable']) {
            return response('', 416, [
                'Content-Range' => "bytes */{$size}",
                'Accept-Ranges' => 'bytes',
            ]);
        }

        $start = $range['start'];
        $end = $range['end'];
        $length = ($end - $start) + 1;
        $stream = $this->audioStream($disk, $evaluation->feedback_audio_path, $start);

        if ($stream === false) {
            abort(404, 'Audio file not found.');
        }

        $headers = [
            'Content-Type' => $mime,
            'Content-Length' => (string) $length,
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline; filename="'.addslashes($fileName).'"',
            'Cache-Control' => 'private, max-age=300',
        ];

        if ($range['partial']) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return response()->stream(function () use ($stream, $length) {
            $remaining = $length;

            while ($remaining > 0 && ! feof($stream)) {
                $chunk = fread($stream, min(8192, $remaining));

                if ($chunk === false || $chunk === '') {
                    break;
                }

                echo $chunk;
                $remaining -= strlen($chunk);

                if (function_exists('flush')) {
                    flush();
                }
            }

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $range['partial'] ? 206 : 200, $headers);
    }

    /**
     * @return array{satisfiable: bool, partial: bool, start: int, end: int}
     */
    private function audioRange(?string $header, int $size): array
    {
        $default = [
            'satisfiable' => true,
            'partial' => false,
            'start' => 0,
            'end' => max(0, $size - 1),
        ];

        if (! $header || ! str_starts_with($header, 'bytes=')) {
            return $default;
        }

        $range = trim(explode(',', substr($header, 6), 2)[0] ?? '');

        if (! preg_match('/^(\d*)-(\d*)$/', $range, $matches)) {
            return ['satisfiable' => false, 'partial' => true, 'start' => 0, 'end' => 0];
        }

        $startRaw = $matches[1];
        $endRaw = $matches[2];

        if ($startRaw === '' && $endRaw === '') {
            return ['satisfiable' => false, 'partial' => true, 'start' => 0, 'end' => 0];
        }

        if ($startRaw === '') {
            $suffixLength = (int) $endRaw;

            if ($suffixLength <= 0) {
                return ['satisfiable' => false, 'partial' => true, 'start' => 0, 'end' => 0];
            }

            $start = max(0, $size - $suffixLength);
            $end = $size - 1;
        } else {
            $start = (int) $startRaw;
            $end = $endRaw !== '' ? (int) $endRaw : $size - 1;
        }

        if ($start >= $size || $end < $start) {
            return ['satisfiable' => false, 'partial' => true, 'start' => 0, 'end' => 0];
        }

        return [
            'satisfiable' => true,
            'partial' => true,
            'start' => $start,
            'end' => min($end, $size - 1),
        ];
    }

    private function audioStream($disk, string $filePath, int $start)
    {
        if (method_exists($disk, 'path')) {
            $absolutePath = $disk->path($filePath);

            if (is_file($absolutePath)) {
                $stream = fopen($absolutePath, 'rb');

                if (is_resource($stream)) {
                    fseek($stream, $start);

                    return $stream;
                }
            }
        }

        $stream = $disk->readStream($filePath);

        if (is_resource($stream)) {
            $meta = stream_get_meta_data($stream);

            if (($meta['seekable'] ?? false) === true) {
                fseek($stream, $start);

                return $stream;
            }

            fclose($stream);
        }

        $memory = fopen('php://temp', 'r+b');

        if (! is_resource($memory)) {
            return false;
        }

        fwrite($memory, $disk->get($filePath));
        rewind($memory);
        fseek($memory, $start);

        return $memory;
    }

    public function reanalyze(Evaluation $evaluation)
    {
        $this->authorize('reanalyze', $evaluation);

        if ($evaluation->type !== 'ai') {
            return back()->with('error', 'Solo se puede reanalizar una evaluación IA.');
        }

        if ($evaluation->isVisibleToAgent()) {
            return back()->with('error', 'No se puede reanalizar una evaluación ya publicada al asesor.');
        }

        $fromStatus = $evaluation->status;

        $evaluation->update([
            'status' => Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
            'reanalysis_requested_at' => now(),
            'reanalysis_requested_by' => auth()->id(),
        ]);

        $evaluation->recordAuditEvent('reanalyze_requested', auth()->user(), [
            'interaction_id' => $evaluation->interaction_id,
        ], $fromStatus, Evaluation::STATUS_AI_REANALYSIS_REQUESTED);

        $evaluation->interaction->update(['status' => 'queued']);
        ScoreTranscriptJob::dispatch($evaluation->interaction_id)->onQueue('ai-scoring');

        return redirect()->route('evaluations.show', $evaluation)
            ->with('success', 'Reanálisis IA enviado a cola.');
    }

    public function toggleGold(Evaluation $evaluation)
    {
        if (! auth()->user()->hasAnyRole(['admin', 'qa_manager'])) {
            abort(403);
        }

        if ($evaluation->type !== 'manual' || ! $evaluation->isVisibleToAgent()) {
            return back()->with('error', 'Solo una evaluación final corregida puede usarse como referencia IA.');
        }

        $evaluation->is_gold = ! $evaluation->is_gold;
        $evaluation->save();

        $evaluation->recordAuditEvent($evaluation->is_gold ? 'gold_marked' : 'gold_unmarked', auth()->user(), [
            'is_gold' => $evaluation->is_gold,
        ]);

        $message = $evaluation->is_gold
            ? 'Evaluación marcada como Golden Record (Referencia para IA).'
            : 'Evaluación desmarcada como Golden Record.';

        return back()->with('success', $message);
    }

    public function close(Request $request, Evaluation $evaluation)
    {
        $this->authorize('close', $evaluation);

        $validated = $request->validate([
            'closure_reason' => 'nullable|string|max:1000',
        ]);

        if (! $evaluation->canBeClosed()) {
            return back()->with('error', 'Esta evaluación no se puede cerrar desde su estado actual.');
        }

        $fromStatus = $evaluation->status;

        $evaluation->update([
            'status' => Evaluation::STATUS_CLOSED,
            'previous_status_before_close' => $fromStatus,
            'closed_at' => now(),
            'closed_by' => auth()->id(),
            'closure_reason' => $validated['closure_reason'] ?? null,
        ]);

        $evaluation->recordAuditEvent('closed', auth()->user(), [
            'reason_present' => filled($validated['closure_reason'] ?? null),
        ], $fromStatus, Evaluation::STATUS_CLOSED);

        return redirect()->route('evaluations.show', $evaluation)
            ->with('success', 'Evaluación cerrada.');
    }

    public function reopen(Evaluation $evaluation)
    {
        $this->authorize('reopen', $evaluation);

        if (! $evaluation->canBeReopened()) {
            return back()->with('error', 'Esta evaluación no tiene un estado previo para reabrir.');
        }

        $fromStatus = $evaluation->status;
        $toStatus = $evaluation->previous_status_before_close;

        $evaluation->update([
            'status' => $toStatus,
            'previous_status_before_close' => null,
            'closed_at' => null,
            'closed_by' => null,
            'closure_reason' => null,
            'reopened_at' => now(),
            'reopened_by' => auth()->id(),
        ]);

        $evaluation->recordAuditEvent('reopened', auth()->user(), [], $fromStatus, $toStatus);

        return redirect()->route('evaluations.show', $evaluation)
            ->with('success', 'Evaluación reabierta.');
    }
}
