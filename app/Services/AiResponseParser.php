<?php

namespace App\Services;

use App\Models\Evaluation;
use Illuminate\Support\Facades\Log;

/**
 * Parses AI provider JSON responses with 6-layer fallback recovery.
 * Extracted from AIEvaluationService for single-responsibility.
 */
class AiResponseParser
{
    /**
     * Parse JSON response from AI provider with progressive fallback recovery.
     *
     * Recovery layers:
     *  1. Direct json_decode
     *  2. Remove trailing commas
     *  3. Strip control characters
     *  4. Escape unescaped control chars inside strings
     *  5. Extract first {…} block
     *  6. Regex-based item recovery (recoverEvaluationJsonPayload)
     */
    public function parse(?string $content): ?array
    {
        if (empty($content)) {
            return null;
        }

        $jsonString = $content;

        // Remove markdown code blocks if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $jsonString = $matches[1];
        }

        // Attempt normal decode
        $result = json_decode($jsonString, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        // Common fix 1: Remove trailing commas
        $jsonString = preg_replace('/,\s*}/', '}', $jsonString);
        $jsonString = preg_replace('/,\s*]/', ']', $jsonString);

        $result = json_decode($jsonString, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        // Common fix 2: Remove control characters that break JSON (except newlines)
        $cleanJson = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', '', $jsonString);
        $result = json_decode($cleanJson, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        // Common fix 3: Escape unescaped control characters inside JSON strings (preserves formatting)
        $cleanJson = preg_replace_callback('/"(?:\\\\.|[^"\\\\])*"/', function ($matches) {
            return str_replace(
                ["\n", "\r", "\t"],
                ['\\n', '\\r', '\\t'],
                preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $matches[0])
            );
        }, $jsonString);
        $result = json_decode($cleanJson, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        // Last resort: find first { and last }
        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $jsonString = substr($content, $start, $end - $start + 1);
            $cleanJson = preg_replace_callback('/"(?:\\\\.|[^"\\\\])*"/', function ($matches) {
                return str_replace(["\n", "\r", "\t"], ['\\n', '\\r', '\\t'], preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $matches[0]));
            }, $jsonString);
            $result = json_decode($cleanJson, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $result;
            }
        }

        $recovered = $this->recoverEvaluationJsonPayload($content);
        if ($recovered) {
            Log::warning('Respuesta JSON de IA recuperada con parser tolerante.');

            return $recovered;
        }

        Log::error('Error parseando respuesta JSON: '.json_last_error_msg());
        Log::error('Content snippet: '.substr($content, 0, 500).' ... '.substr($content, -500));

        return null;
    }

    /**
     * Regex-based recovery: extract individual evaluation items by matching
     * "id": N, "status": "..." patterns when full JSON parsing fails.
     */
    public function recoverEvaluationJsonPayload(string $content): ?array
    {
        if (! preg_match_all('/"id"\s*:\s*(\d+)\s*,\s*"status"\s*:\s*"(compliant|non_compliant|not_found)"/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $items = [];
        $ids = $matches[1];
        $statuses = $matches[2];

        foreach ($ids as $index => $idMatch) {
            $itemStart = max(0, $matches[0][$index][1] - 1);
            $nextStart = isset($matches[0][$index + 1])
                ? max(0, $matches[0][$index + 1][1] - 1)
                : strlen($content);
            $slice = substr($content, $itemStart, max(1, $nextStart - $itemStart));

            $confidence = $this->extractJsonLikeNumber($slice, 'confidence');

            $items[] = [
                'id' => (int) $idMatch[0],
                'status' => strtolower($statuses[$index][0]),
                'evidence_quote' => $this->extractJsonLikeString($slice, 'evidence_quote') ?? '',
                'confidence' => $confidence !== null ? max(0, min(1, $confidence)) : null,
                'notes' => $this->extractJsonLikeString($slice, 'notes'),
            ];
        }

        if ($items === []) {
            return null;
        }

        return [
            'items' => $items,
            'feedback' => $this->extractFeedbackObject($content),
            'general_feedback' => $this->extractJsonLikeString($content, 'general_feedback')
                ?? 'Evaluación generada por IA. Revise los criterios y evidencias recuperadas en el detalle.',
        ];
    }

    /**
     * Extract the "feedback" object from malformed JSON content.
     */
    public function extractFeedbackObject(string $content): ?array
    {
        if (! preg_match('/"feedback"\s*:\s*\{([\s\S]*?)\}\s*(?:,|\})/u', $content, $matches)) {
            return null;
        }

        $slice = $matches[1];
        $feedback = [];
        foreach (array_keys(Evaluation::AI_FEEDBACK_SECTIONS) as $field) {
            $feedback[$field] = $this->extractJsonLikeString('{'.$slice.'}', $field) ?? '';
        }

        return array_filter($feedback, fn ($value) => filled($value)) !== []
            ? $feedback
            : null;
    }

    /**
     * Extract a string field value from near-JSON content using regex.
     */
    public function extractJsonLikeString(string $source, string $field): ?string
    {
        $fieldPattern = preg_quote($field, '/');
        if (! preg_match('/"'.$fieldPattern.'"\s*:\s*"(.*?)(?<!\\\\)"\s*(?:,|\})/s', $source, $matches)) {
            return null;
        }

        $value = $matches[1];
        $decoded = json_decode('"'.$value.'"', true);
        if (json_last_error() === JSON_ERROR_NONE && is_string($decoded)) {
            return trim(preg_replace('/\s+/', ' ', $decoded));
        }

        return trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n", "\t"], ' ', $value)));
    }

    /**
     * Extract a numeric field value from near-JSON content using regex.
     */
    public function extractJsonLikeNumber(string $source, string $field): ?float
    {
        $fieldPattern = preg_quote($field, '/');
        if (! preg_match('/"'.$fieldPattern.'"\s*:\s*(-?(?:\d*\.\d+|\d+))/', $source, $matches)) {
            return null;
        }

        return (float) $matches[1];
    }
}
