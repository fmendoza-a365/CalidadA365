<?php

namespace App\Support;

use Illuminate\Support\Arr;

class TranscriptAudioTimeline
{
    private const SENTIMENTS = [
        'positivo' => ['score' => 0.7, 'color' => '#10b981', 'label' => 'Positivo'],
        'negativo' => ['score' => -0.7, 'color' => '#f43f5e', 'label' => 'Negativo'],
        'neutro' => ['score' => 0.0, 'color' => '#64748b', 'label' => 'Neutro'],
        'mixto' => ['score' => 0.2, 'color' => '#f59e0b', 'label' => 'Mixto'],
    ];

    /**
     * @param  array<int, array<string, mixed>>  $turns
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function build(array $turns, ?int $audioDuration, array $metadata): array
    {
        $duration = $this->duration($turns, $audioDuration);
        $segments = $this->segments($turns, $duration, $metadata);
        $turns = $this->mergeSegmentDataIntoTurns($turns, $segments);

        return [
            'duration' => $duration,
            'duration_label' => $this->formatSeconds($duration),
            'segments' => $segments,
            'bars' => $this->bars($segments, $duration),
            'summary' => $this->summary($segments, $duration, $metadata),
            'turns' => $turns,
            'sentiment' => $metadata['sentiment'] ?? null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $turns
     */
    private function duration(array $turns, ?int $audioDuration): int
    {
        $lastTimestamp = collect($turns)->max('timestamp_seconds') ?? 0;

        return max((int) ($audioDuration ?: 0), (int) $lastTimestamp + 10, 1);
    }

    /**
     * @param  array<int, array<string, mixed>>  $turns
     * @param  array<string, mixed>  $metadata
     * @return array<int, array<string, mixed>>
     */
    private function segments(array $turns, int $duration, array $metadata): array
    {
        $timedTurns = collect($turns)
            ->filter(fn (array $turn): bool => is_numeric($turn['timestamp_seconds'] ?? null))
            ->values();

        $metadataSegments = collect($metadata['sentiment_segments'] ?? $metadata['emotion_segments'] ?? []);
        $segments = [];

        foreach ($timedTurns as $index => $turn) {
            $start = (int) $turn['timestamp_seconds'];
            $nextStart = $timedTurns[$index + 1]['timestamp_seconds'] ?? null;
            $end = is_numeric($nextStart) ? (int) $nextStart : min($duration, $start + 8);

            if ($end <= $start) {
                $end = min($duration, $start + 3);
            }

            $metadataSegment = $this->matchingMetadataSegment($metadataSegments->all(), $turn, $index, $start);
            $mood = $this->segmentMood($turn, $metadataSegment, $metadata);

            $segments[] = [
                'id' => 'segment-'.$index,
                'turn_id' => $turn['id'] ?? "turn-{$index}",
                'index' => $index,
                'start' => $start,
                'end' => max($end, $start + 1),
                'start_label' => $this->formatSeconds($start),
                'end_label' => $this->formatSeconds(max($end, $start + 1)),
                'left' => round(($start / $duration) * 100, 4),
                'width' => max(round(((max($end, $start + 1) - $start) / $duration) * 100, 4), 0.8),
                'speaker' => $turn['speaker'] ?? 'system',
                'label' => $turn['label'] ?? 'Sistema',
                'message' => $turn['message'] ?? '',
                ...$mood,
            ];
        }

        return $segments;
    }

    /**
     * @param  array<int, mixed>  $metadataSegments
     * @param  array<string, mixed>  $turn
     */
    private function matchingMetadataSegment(array $metadataSegments, array $turn, int $index, int $start): ?array
    {
        foreach ($metadataSegments as $segment) {
            if (! is_array($segment)) {
                continue;
            }

            if (isset($segment['turn_id']) && $segment['turn_id'] === ($turn['id'] ?? null)) {
                return $segment;
            }

            if (isset($segment['index']) && (int) $segment['index'] === $index) {
                return $segment;
            }

            if (isset($segment['start']) && abs((int) $segment['start'] - $start) <= 1) {
                return $segment;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $turn
     * @param  array<string, mixed>|null  $metadataSegment
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function segmentMood(array $turn, ?array $metadataSegment, array $metadata): array
    {
        $sentiment = (string) ($metadataSegment['sentiment'] ?? $this->inferSentiment($turn, $metadata));
        $sentiment = array_key_exists($sentiment, self::SENTIMENTS) ? $sentiment : 'neutro';
        $emotion = (string) ($metadataSegment['emotion'] ?? $this->inferEmotion($turn, $sentiment));
        $score = (float) ($metadataSegment['score'] ?? self::SENTIMENTS[$sentiment]['score']);
        $intensity = (int) ($metadataSegment['intensity'] ?? $this->intensityFromMessage($turn['message'] ?? ''));

        return [
            'sentiment' => $sentiment,
            'sentiment_label' => self::SENTIMENTS[$sentiment]['label'],
            'emotion' => $emotion,
            'emotion_label' => str($emotion)->replace('_', ' ')->title()->toString(),
            'score' => max(min($score, 1), -1),
            'intensity' => max(min($intensity, 100), 20),
            'color' => self::SENTIMENTS[$sentiment]['color'],
        ];
    }

    /**
     * @param  array<string, mixed>  $turn
     * @param  array<string, mixed>  $metadata
     */
    private function inferSentiment(array $turn, array $metadata): string
    {
        $message = mb_strtolower((string) ($turn['message'] ?? ''), 'UTF-8');

        if (str_contains($message, 'preocupa') || str_contains($message, 'inconveniente') || str_contains($message, 'urgencia')) {
            return 'mixto';
        }

        if (str_contains($message, 'gracias') || str_contains($message, 'perfecto') || str_contains($message, 'correcto')) {
            return 'positivo';
        }

        return (string) Arr::get($metadata, 'sentiment.overall', 'neutro');
    }

    /**
     * @param  array<string, mixed>  $turn
     */
    private function inferEmotion(array $turn, string $sentiment): string
    {
        $message = mb_strtolower((string) ($turn['message'] ?? ''), 'UTF-8');

        if (str_contains($message, 'preocupa') || str_contains($message, 'urgencia')) {
            return 'preocupacion';
        }

        if (str_contains($message, 'gracias') || str_contains($message, 'perfecto')) {
            return 'satisfaccion';
        }

        return match ($sentiment) {
            'positivo' => 'confianza',
            'negativo' => 'frustracion',
            'mixto' => 'tension_controlada',
            default => 'calma',
        };
    }

    private function intensityFromMessage(string $message): int
    {
        return min(88, max(32, 28 + (mb_strlen($message, 'UTF-8') % 56)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $turns
     * @param  array<int, array<string, mixed>>  $segments
     * @return array<int, array<string, mixed>>
     */
    private function mergeSegmentDataIntoTurns(array $turns, array $segments): array
    {
        $segmentsByTurn = collect($segments)->keyBy('turn_id');

        return collect($turns)
            ->map(function (array $turn) use ($segmentsByTurn): array {
                $segment = $segmentsByTurn->get($turn['id'] ?? '');

                if (! $segment) {
                    return $turn;
                }

                return array_merge($turn, [
                    'start_seconds' => $segment['start'],
                    'end_seconds' => $segment['end'],
                    'sentiment' => $segment['sentiment'],
                    'emotion' => $segment['emotion'],
                    'emotion_label' => $segment['emotion_label'],
                    'sentiment_color' => $segment['color'],
                ]);
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $segments
     * @return array<int, array<string, mixed>>
     */
    private function bars(array $segments, int $duration): array
    {
        $barCount = 120;
        $bars = [];

        for ($index = 0; $index < $barCount; $index++) {
            $second = ($duration / $barCount) * ($index + 0.5);
            $segment = $this->segmentAtSecond($segments, $second);
            $phase = sin($index * 0.62) + cos($index * 0.23);
            $speakerLift = ($segment['speaker'] ?? '') === 'client' ? 8 : 0;
            $height = 24 + abs($phase * 18) + $speakerLift + (($segment['intensity'] ?? 50) * 0.28);

            $bars[] = [
                'index' => $index,
                'height' => round(min($height, 92), 2),
                'time' => round($second, 2),
                'color' => $segment['color'] ?? '#64748b',
                'speaker' => $segment['speaker'] ?? 'system',
                'sentiment' => $segment['sentiment'] ?? 'neutro',
            ];
        }

        return $bars;
    }

    /**
     * @param  array<int, array<string, mixed>>  $segments
     */
    private function segmentAtSecond(array $segments, float $second): ?array
    {
        foreach ($segments as $segment) {
            if ($second >= $segment['start'] && $second < $segment['end']) {
                return $segment;
            }
        }

        return $segments[array_key_last($segments)] ?? null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $segments
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function summary(array $segments, int $duration, array $metadata): array
    {
        $agentSeconds = $this->speakerSeconds($segments, 'agent');
        $clientSeconds = $this->speakerSeconds($segments, 'client');
        $emotions = collect($segments)->countBy('emotion')->sortDesc();
        $sentiments = collect($segments)->countBy('sentiment')->all();
        $firstScore = (float) ($segments[0]['score'] ?? 0);
        $lastScore = (float) ($segments[array_key_last($segments)]['score'] ?? 0);

        return [
            'duration_label' => $this->formatSeconds($duration),
            'agent_talk_label' => $this->formatSeconds($agentSeconds),
            'client_talk_label' => $this->formatSeconds($clientSeconds),
            'agent_talk_percent' => $duration > 0 ? round(($agentSeconds / $duration) * 100) : 0,
            'client_talk_percent' => $duration > 0 ? round(($clientSeconds / $duration) * 100) : 0,
            'handoffs' => $this->handoffs($segments),
            'dominant_emotion' => $emotions->keys()->first() ?: 'calma',
            'dominant_emotion_label' => str($emotions->keys()->first() ?: 'calma')->replace('_', ' ')->title()->toString(),
            'trend' => $lastScore >= $firstScore ? 'Mejora emocional' : 'Deterioro emocional',
            'overall_sentiment' => Arr::get($metadata, 'sentiment.overall', 'neutro'),
            'sentiments' => $sentiments,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $segments
     */
    private function speakerSeconds(array $segments, string $speaker): int
    {
        return (int) collect($segments)
            ->filter(fn (array $segment): bool => ($segment['speaker'] ?? null) === $speaker)
            ->sum(fn (array $segment): int => max(0, (int) $segment['end'] - (int) $segment['start']));
    }

    /**
     * @param  array<int, array<string, mixed>>  $segments
     */
    private function handoffs(array $segments): int
    {
        $handoffs = 0;
        $previous = null;

        foreach ($segments as $segment) {
            $speaker = $segment['speaker'] ?? null;

            if (! in_array($speaker, ['agent', 'client'], true)) {
                continue;
            }

            if ($previous !== null && $previous !== $speaker) {
                $handoffs++;
            }

            $previous = $speaker;
        }

        return $handoffs;
    }

    private function formatSeconds(int|float $seconds): string
    {
        $seconds = max((int) round($seconds), 0);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds);
        }

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }
}
