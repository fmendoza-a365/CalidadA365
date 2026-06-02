<?php

namespace App\Support;

class TranscriptConversationParser
{
    private const AGENT_SPEAKERS = [
        'agente',
        'agent',
        'asesor',
        'asesora',
        'ejecutivo',
        'ejecutiva',
        'operador',
        'operadora',
        'representante',
        'monitor',
    ];

    private const CLIENT_SPEAKERS = [
        'cliente',
        'client',
        'usuario',
        'usuaria',
        'customer',
        'asegurado',
        'asegurada',
    ];

    private const SYSTEM_SPEAKERS = [
        'sistema',
        'ivr',
        'bot',
        'contexto',
    ];

    /**
     * @return array<int, array{
     *     speaker: string,
     *     label: string,
     *     raw_speaker: string,
     *     message: string,
     *     timestamp: string|null,
     *     timestamp_seconds: int|null,
     *     is_agent: bool,
     *     is_client: bool,
     *     is_system: bool
     * }>
     */
    public function parse(?string $rawTranscript): array
    {
        $text = $this->normalize($rawTranscript);

        if ($text === '') {
            return [];
        }

        $chunks = $this->splitIntoSpeakerChunks($text);
        $turns = [];

        foreach ($chunks ?: [] as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '') {
                continue;
            }

            $turn = $this->parseChunk($chunk);

            if ($turn !== null) {
                $turns[] = $turn;

                continue;
            }

            if ($this->looksLikeTechnicalPayload($chunk)) {
                continue;
            }

            $turns[] = $this->makeTurn('system', 'Contexto', $chunk);
        }

        return collect($turns)
            ->filter(fn (array $turn): bool => $turn['message'] !== '')
            ->values()
            ->map(function (array $turn, int $index): array {
                $turn['id'] = "turn-{$index}";

                return $turn;
            })
            ->all();
    }

    private function normalize(?string $rawTranscript): string
    {
        $text = trim((string) $rawTranscript);

        if ($text === '') {
            return '';
        }

        $jsonTranscript = $this->extractTranscriptFromJson($text);

        if ($jsonTranscript !== null) {
            $text = $jsonTranscript;
        }

        $text = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim($text);
    }

    /**
     * @return array<string, mixed>|array<int, mixed>|null
     */
    public function extractStructuredPayload(?string $rawTranscript): ?array
    {
        $text = $this->stripJsonFence(trim((string) $rawTranscript));

        if ($text === '') {
            return null;
        }

        $decoded = json_decode($text, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        foreach ($this->jsonObjectCandidates($text) as $candidate) {
            $decoded = json_decode($candidate, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function extractTranscriptFromJson(string $text): ?string
    {
        $trimmed = $this->stripJsonFence(trim($text));

        if (! str_starts_with($trimmed, '{') && ! str_starts_with($trimmed, '[')) {
            if (! str_contains($trimmed, '"transcript"') && ! str_contains($trimmed, "'transcript'")) {
                return null;
            }
        }

        $decoded = $this->extractStructuredPayload($trimmed);

        if (! is_array($decoded)) {
            return $this->extractTranscriptField($trimmed);
        }

        foreach (['transcript', 'transcription', 'text', 'content'] as $key) {
            if (isset($decoded[$key]) && is_string($decoded[$key])) {
                return $decoded[$key];
            }
        }

        if (array_is_list($decoded)) {
            $segments = $this->transcriptFromSegments($decoded);

            if ($segments !== '') {
                return $segments;
            }
        }

        if (isset($decoded['segments']) && is_array($decoded['segments'])) {
            $segments = $this->transcriptFromSegments($decoded['segments']);

            if ($segments !== '') {
                return $segments;
            }
        }

        return null;
    }

    private function stripJsonFence(string $text): string
    {
        $text = trim($text);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/isu', $text, $matches)) {
            return trim($matches[1]);
        }

        $text = preg_replace('/^```(?:json)?\s*/iu', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/u', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array<int, string>
     */
    private function jsonObjectCandidates(string $text): array
    {
        $candidates = [];
        $depth = 0;
        $start = null;
        $inString = false;
        $escaped = false;
        $length = strlen($text);

        for ($index = 0; $index < $length; $index++) {
            $char = $text[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;

                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{') {
                if ($depth === 0) {
                    $start = $index;
                }

                $depth++;

                continue;
            }

            if ($char === '}' && $depth > 0) {
                $depth--;

                if ($depth === 0 && $start !== null) {
                    $candidates[] = substr($text, $start, $index - $start + 1);
                    $start = null;
                }
            }
        }

        return $candidates;
    }

    private function extractTranscriptField(string $text): ?string
    {
        if (! preg_match('/["\']transcript["\']\s*:\s*"((?:\\\\.|[^"\\\\])*)"/su', $text, $matches)) {
            return null;
        }

        $decoded = json_decode('"'.$matches[1].'"');

        if (is_string($decoded) && trim($decoded) !== '') {
            return $decoded;
        }

        $fallback = str_replace(['\\r\\n', '\\n', '\\r', '\\t'], ["\n", "\n", "\n", "\t"], $matches[1]);

        return trim($fallback) !== '' ? $fallback : null;
    }

    /**
     * @param  array<int, mixed>  $segments
     */
    private function transcriptFromSegments(array $segments): string
    {
        $lines = [];

        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }

            $message = $segment['text'] ?? $segment['message'] ?? $segment['content'] ?? null;

            if (! is_string($message) || trim($message) === '') {
                continue;
            }

            $speaker = $segment['speaker'] ?? $segment['role'] ?? 'Sistema';
            $speaker = is_string($speaker) && trim($speaker) !== '' ? $speaker : 'Sistema';
            $timestamp = $this->formatSegmentTimestamp($segment['start'] ?? $segment['timestamp'] ?? null);
            $prefix = $timestamp ? "[{$timestamp}] " : '';

            $lines[] = "{$prefix}{$speaker}: {$message}";
        }

        return implode("\n", $lines);
    }

    private function formatSegmentTimestamp(mixed $value): ?string
    {
        if (is_string($value) && preg_match('/^\d{1,2}:\d{2}(?::\d{2})?$/', trim($value))) {
            return trim($value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        $seconds = (int) floor((float) $value);
        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }

    /**
     * @return array<int, string>
     */
    private function splitIntoSpeakerChunks(string $text): array
    {
        preg_match_all($this->speakerMarkerPattern(), $text, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            return [$text];
        }

        $chunks = [];
        $markers = $matches[0];
        $firstOffset = $markers[0][1];

        if ($firstOffset > 0) {
            $chunks[] = substr($text, 0, $firstOffset);
        }

        foreach ($markers as $index => $marker) {
            $start = $marker[1];
            $end = $markers[$index + 1][1] ?? strlen($text);

            $chunks[] = substr($text, $start, $end - $start);
        }

        return $chunks;
    }

    private function speakerMarkerPattern(): string
    {
        return '/(?<![\pL\pN])(?:\[\d{1,2}:\d{2}(?::\d{2})?\]\s*|\(\d{1,2}:\d{2}(?::\d{2})?\)\s*|\d{1,2}:\d{2}(?::\d{2})?\s+)?(?:'.$this->speakerPattern().')\s*:/iu';
    }

    private function parseChunk(string $chunk): ?array
    {
        $pattern = '/^\s*(?:\[(?<timestamp_bracket>\d{1,2}:\d{2}(?::\d{2})?)\]\s*|\((?<timestamp_paren>\d{1,2}:\d{2}(?::\d{2})?)\)\s*|(?<timestamp_plain>\d{1,2}:\d{2}(?::\d{2})?)\s+)?(?<speaker>'.$this->speakerPattern().')\s*:\s*(?<message>.*)$/isu';

        if (! preg_match($pattern, $chunk, $matches)) {
            return null;
        }

        $rawSpeaker = trim($matches['speaker']);
        $message = trim($matches['message'] ?? '', " \t\n\r\0\x0B\"'");

        if ($message === '') {
            return null;
        }

        $timestamp = $matches['timestamp_bracket']
            ?? $matches['timestamp_paren']
            ?? $matches['timestamp_plain']
            ?? null;

        $type = $this->speakerType($rawSpeaker);

        if ($type === 'system' && $this->looksLikeTechnicalPayload($message)) {
            return null;
        }

        return $this->makeTurn($type, $this->speakerLabel($type, $rawSpeaker), $message, $timestamp, $rawSpeaker);
    }

    private function looksLikeTechnicalPayload(string $text): bool
    {
        $trimmed = $this->stripJsonFence(trim($text));

        if ($trimmed === '') {
            return false;
        }

        if ($this->extractStructuredPayload($trimmed) !== null) {
            return true;
        }

        return (str_starts_with($trimmed, '{') || str_contains($trimmed, '"transcript"'))
            && (str_contains($trimmed, '"sentiment"') || str_contains($trimmed, '"transcript"'));
    }

    private function makeTurn(
        string $type,
        string $label,
        string $message,
        ?string $timestamp = null,
        ?string $rawSpeaker = null
    ): array {
        return [
            'speaker' => $type,
            'label' => $label,
            'raw_speaker' => $rawSpeaker ?? $label,
            'message' => trim($message),
            'timestamp' => $timestamp,
            'timestamp_seconds' => $timestamp ? $this->timestampToSeconds($timestamp) : null,
            'is_agent' => $type === 'agent',
            'is_client' => $type === 'client',
            'is_system' => $type === 'system',
        ];
    }

    private function timestampToSeconds(string $timestamp): int
    {
        $parts = array_map('intval', explode(':', $timestamp));

        if (count($parts) === 3) {
            return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
        }

        return ($parts[0] * 60) + ($parts[1] ?? 0);
    }

    private function speakerType(string $speaker): string
    {
        $normalized = $this->normalizeSpeaker($speaker);

        return match (true) {
            in_array($normalized, self::AGENT_SPEAKERS, true) => 'agent',
            in_array($normalized, self::CLIENT_SPEAKERS, true) => 'client',
            in_array($normalized, self::SYSTEM_SPEAKERS, true) => 'system',
            default => 'system',
        };
    }

    private function speakerLabel(string $type, string $rawSpeaker): string
    {
        return match ($type) {
            'agent' => 'Agente',
            'client' => 'Cliente',
            'system' => $this->normalizeSpeaker($rawSpeaker) === 'contexto' ? 'Contexto' : 'Sistema',
            default => 'Sistema',
        };
    }

    private function normalizeSpeaker(string $speaker): string
    {
        $speaker = mb_strtolower(trim($speaker), 'UTF-8');

        return strtr($speaker, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
    }

    private function speakerPattern(): string
    {
        $speakers = array_merge(self::AGENT_SPEAKERS, self::CLIENT_SPEAKERS, self::SYSTEM_SPEAKERS);
        $speakers = array_unique(array_map(fn (string $speaker): string => preg_quote($speaker, '/'), $speakers));

        return implode('|', $speakers);
    }
}
