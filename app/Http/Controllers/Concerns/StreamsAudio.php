<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Interaction;
use App\Support\TranscriptConversationParser;

trait StreamsAudio
{
    /**
     * Parse HTTP Range header into start/end byte positions.
     *
     * @return array{satisfiable: bool, partial: bool, start: int, end: int}
     */
    protected function audioRange(?string $header, int $size): array
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

    /**
     * Open a seekable stream for the given file, starting at the specified byte offset.
     *
     * @param  mixed  $disk
     * @return resource|false
     */
    protected function audioStream($disk, string $filePath, int $start)
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

    /**
     * Extract or parse audio metadata (sentiment, acoustic analysis, etc.) from an interaction.
     */
    protected function audioMetadata(Interaction $interaction, TranscriptConversationParser $conversationParser): array
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
}
