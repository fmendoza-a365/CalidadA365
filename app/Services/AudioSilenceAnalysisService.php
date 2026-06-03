<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class AudioSilenceAnalysisService
{
    /**
     * @return array<string, mixed>
     */
    public function analyzeStoredFile(string $filePath, ?int $durationSeconds = null): array
    {
        $disk = Storage::disk(config('filesystems.default', 'local'));

        if (! $disk->exists($filePath)) {
            return $this->emptyResult($durationSeconds, 'unavailable');
        }

        if (method_exists($disk, 'path')) {
            $absolutePath = $disk->path($filePath);
            $fromFfmpeg = $this->analyzeWithFfmpeg($absolutePath, $durationSeconds);

            if ($fromFfmpeg !== null) {
                return $fromFfmpeg;
            }
        }

        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'wav') {
            $fromWav = $this->analyzePcmWavBytes($disk->get($filePath), $durationSeconds);

            if ($fromWav !== null) {
                return $fromWav;
            }
        }

        return $this->emptyResult($durationSeconds, 'unavailable');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function analyzeWithFfmpeg(string $absolutePath, ?int $durationSeconds): ?array
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $binary = config('services.ffmpeg.path')
            ?: env('FFMPEG_PATH')
            ?: (new ExecutableFinder)->find('ffmpeg');

        if (! $binary) {
            return null;
        }

        try {
            $process = new Process([
                $binary,
                '-hide_banner',
                '-nostdin',
                '-i',
                $absolutePath,
                '-af',
                sprintf('silencedetect=noise=%sdB:d=%s', $this->thresholdDb(), $this->minimumSilenceSeconds()),
                '-f',
                'null',
                '-',
            ]);
            $process->setTimeout($this->timeoutFor($durationSeconds));
            $process->run();

            if (! $process->isSuccessful()) {
                Log::debug('AudioSilenceAnalysisService: ffmpeg silence detection failed', [
                    'file' => $absolutePath,
                    'error' => $process->getErrorOutput(),
                ]);

                return null;
            }

            $segments = $this->parseSilencedetectOutput($process->getErrorOutput(), $durationSeconds);

            return $this->resultFromSegments($segments, $durationSeconds, 'ffmpeg');
        } catch (\Throwable $e) {
            Log::debug('AudioSilenceAnalysisService: ffmpeg silence detection exception', [
                'file' => $absolutePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<int, array<string, float>>
     */
    private function parseSilencedetectOutput(string $output, ?int $durationSeconds): array
    {
        $segments = [];
        $currentStart = null;

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (preg_match('/silence_start:\s*([0-9.]+)/', $line, $matches)) {
                $currentStart = (float) $matches[1];
                continue;
            }

            if (preg_match('/silence_end:\s*([0-9.]+)\s*\|\s*silence_duration:\s*([0-9.]+)/', $line, $matches)) {
                $end = (float) $matches[1];
                $duration = (float) $matches[2];
                $start = $currentStart ?? max(0, $end - $duration);
                $segments[] = ['start' => $start, 'end' => $end, 'duration' => $duration];
                $currentStart = null;
            }
        }

        if ($currentStart !== null && $durationSeconds !== null && $durationSeconds > $currentStart) {
            $segments[] = [
                'start' => $currentStart,
                'end' => (float) $durationSeconds,
                'duration' => (float) $durationSeconds - $currentStart,
            ];
        }

        return $segments;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function analyzePcmWavBytes(string $bytes, ?int $durationSeconds): ?array
    {
        $wav = $this->parsePcmWav($bytes);

        if ($wav === null) {
            return null;
        }

        $byteRate = max((int) $wav['byte_rate'], 1);
        $detectedDuration = (int) max(1, round(strlen($wav['data']) / $byteRate));
        $durationSeconds ??= $detectedDuration;
        $windowBytes = max((int) round($byteRate * 0.1), (int) $wav['block_align']);
        $segments = [];
        $currentStart = null;
        $currentEnd = null;

        for ($offset = 0, $length = strlen($wav['data']); $offset < $length; $offset += $windowBytes) {
            $window = substr($wav['data'], $offset, min($windowBytes, $length - $offset));
            $start = $offset / $byteRate;
            $end = ($offset + strlen($window)) / $byteRate;
            $isSilent = $this->pcmWindowDb($window, (int) $wav['bits_per_sample']) <= $this->thresholdDb();

            if ($isSilent) {
                $currentStart ??= $start;
                $currentEnd = $end;
                continue;
            }

            if ($currentStart !== null && $currentEnd !== null) {
                $segments[] = [
                    'start' => $currentStart,
                    'end' => $currentEnd,
                    'duration' => $currentEnd - $currentStart,
                ];
            }

            $currentStart = null;
            $currentEnd = null;
        }

        if ($currentStart !== null && $currentEnd !== null) {
            $segments[] = [
                'start' => $currentStart,
                'end' => $currentEnd,
                'duration' => $currentEnd - $currentStart,
            ];
        }

        return $this->resultFromSegments($segments, $durationSeconds, 'wav_pcm');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parsePcmWav(string $bytes): ?array
    {
        if (strlen($bytes) < 44 || substr($bytes, 0, 4) !== 'RIFF' || substr($bytes, 8, 4) !== 'WAVE') {
            return null;
        }

        $offset = 12;
        $fmt = null;
        $data = null;
        $length = strlen($bytes);

        while ($offset + 8 <= $length) {
            $chunkId = substr($bytes, $offset, 4);
            $chunkSize = unpack('V', substr($bytes, $offset + 4, 4))[1] ?? 0;
            $chunkStart = $offset + 8;

            if ($chunkStart + $chunkSize > $length) {
                break;
            }

            $chunk = substr($bytes, $chunkStart, $chunkSize);

            if ($chunkId === 'fmt ' && strlen($chunk) >= 16) {
                $fmt = unpack('vformat/vchannels/Vsample_rate/Vbyte_rate/vblock_align/vbits_per_sample', substr($chunk, 0, 16));
            } elseif ($chunkId === 'data') {
                $data = $chunk;
            }

            $offset = $chunkStart + $chunkSize + ($chunkSize % 2);
        }

        if (! $fmt || $data === null || (int) $fmt['format'] !== 1 || ! in_array((int) $fmt['bits_per_sample'], [8, 16], true)) {
            return null;
        }

        return [
            'data' => $data,
            'byte_rate' => (int) $fmt['byte_rate'],
            'block_align' => max((int) $fmt['block_align'], 1),
            'bits_per_sample' => (int) $fmt['bits_per_sample'],
        ];
    }

    private function pcmWindowDb(string $window, int $bitsPerSample): float
    {
        if ($window === '') {
            return -120.0;
        }

        $sumSquares = 0.0;
        $samples = 0;

        if ($bitsPerSample === 16) {
            $window = substr($window, 0, strlen($window) - (strlen($window) % 2));

            foreach (unpack('v*', $window) ?: [] as $value) {
                $sample = $value >= 32768 ? $value - 65536 : $value;
                $normalized = $sample / 32768;
                $sumSquares += $normalized * $normalized;
                $samples++;
            }
        } else {
            foreach (unpack('C*', $window) ?: [] as $value) {
                $normalized = ($value - 128) / 128;
                $sumSquares += $normalized * $normalized;
                $samples++;
            }
        }

        if ($samples === 0 || $sumSquares <= 0) {
            return -120.0;
        }

        $rms = sqrt($sumSquares / $samples);

        return 20 * log10(max($rms, 0.000001));
    }

    /**
     * @param  array<int, array<string, float>>  $segments
     * @return array<string, mixed>
     */
    private function resultFromSegments(array $segments, ?int $durationSeconds, string $source): array
    {
        $minimum = $this->minimumSilenceSeconds();
        $segments = collect($segments)
            ->filter(fn (array $segment): bool => ($segment['duration'] ?? 0) >= $minimum)
            ->map(function (array $segment): array {
                $start = max(0.0, (float) $segment['start']);
                $end = max($start, (float) $segment['end']);
                $duration = max(0.0, (float) ($segment['duration'] ?? ($end - $start)));

                return [
                    'start' => round($start, 2),
                    'end' => round($end, 2),
                    'duration' => round($duration, 2),
                    'start_label' => $this->formatSeconds($start),
                    'end_label' => $this->formatSeconds($end),
                    'duration_label' => $this->formatSeconds($duration),
                ];
            })
            ->values();

        $allSegments = $segments->all();
        $total = round((float) $segments->sum('duration'), 2);
        $longest = $segments->sortByDesc('duration')->first();
        $durationSeconds = $durationSeconds !== null && $durationSeconds > 0
            ? $durationSeconds
            : (int) max(1, ceil((float) ($segments->max('end') ?? 0)));

        return [
            'source' => $source,
            'threshold_db' => $this->thresholdDb(),
            'minimum_silence_seconds' => $minimum,
            'duration_seconds' => $durationSeconds,
            'long_pauses' => count($allSegments),
            'total_silence_seconds' => $total,
            'total_silence_label' => $this->formatSeconds($total),
            'silence_ratio' => $durationSeconds > 0 ? round($total / $durationSeconds, 4) : 0.0,
            'longest_silence_seconds' => $longest ? (float) $longest['duration'] : 0.0,
            'longest_silence_label' => $longest ? "{$longest['start_label']}-{$longest['end_label']}" : null,
            'has_dead_air' => count($allSegments) > 0,
            'segments' => array_slice($allSegments, 0, $this->maxSegments()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(?int $durationSeconds, string $source): array
    {
        return [
            'source' => $source,
            'threshold_db' => $this->thresholdDb(),
            'minimum_silence_seconds' => $this->minimumSilenceSeconds(),
            'duration_seconds' => $durationSeconds,
            'long_pauses' => 0,
            'total_silence_seconds' => 0.0,
            'total_silence_label' => '00:00',
            'silence_ratio' => 0.0,
            'longest_silence_seconds' => 0.0,
            'longest_silence_label' => null,
            'has_dead_air' => false,
            'segments' => [],
        ];
    }

    private function thresholdDb(): int
    {
        return (int) config('services.audio_silence.threshold_db', env('AUDIO_SILENCE_THRESHOLD_DB', -45));
    }

    private function minimumSilenceSeconds(): float
    {
        return max(0.5, (float) config('services.audio_silence.minimum_seconds', env('AUDIO_SILENCE_MIN_SECONDS', 2.0)));
    }

    private function maxSegments(): int
    {
        return max(1, (int) config('services.audio_silence.max_segments', env('AUDIO_SILENCE_MAX_SEGMENTS', 24)));
    }

    private function timeoutFor(?int $durationSeconds): int
    {
        return min(300, max(30, (int) (($durationSeconds ?? 60) * 1.5)));
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
