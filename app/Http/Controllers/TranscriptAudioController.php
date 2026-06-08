<?php

namespace App\Http\Controllers;

use App\Models\Interaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TranscriptAudioController extends Controller
{
    use Concerns\StreamsAudio;

    public function download(Interaction $interaction)
    {
        $user = auth()->user();
        if (! Interaction::forUser($user)->where('id', $interaction->id)->exists()) {
            abort(403, 'No tiene permiso para descargar esta transcripción.');
        }

        return Storage::disk($this->privateDisk())->download($interaction->file_path, $interaction->file_name);
    }

    public function audio(Request $request, Interaction $interaction)
    {
        $user = auth()->user();
        if (! Interaction::forUser($user)->where('id', $interaction->id)->exists()) {
            abort(403, 'No tiene permiso para escuchar esta transcripción.');
        }

        if (! $interaction->isAudio()) {
            abort(404, 'This interaction does not have an audio file.');
        }

        $disk = Storage::disk($this->privateDisk());

        if (! $disk->exists($interaction->file_path)) {
            abort(404, 'Audio file not found.');
        }

        $mimeTypes = [
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'm4a' => 'audio/mp4',
            'webm' => 'audio/webm',
        ];

        $extension = strtolower(pathinfo($interaction->file_name, PATHINFO_EXTENSION));
        $mime = $mimeTypes[$extension] ?? 'audio/mpeg';
        $size = (int) $disk->size($interaction->file_path);

        if ($size <= 0) {
            return response('', 200, [
                'Content-Type' => $mime,
                'Content-Length' => '0',
                'Accept-Ranges' => 'bytes',
                'Content-Disposition' => 'inline; filename="'.addslashes($interaction->file_name).'"',
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
        $stream = $this->audioStream($disk, $interaction->file_path, $start);

        if ($stream === false) {
            abort(404, 'Audio file not found.');
        }

        $headers = [
            'Content-Type' => $mime,
            'Content-Length' => (string) $length,
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline; filename="'.addslashes($interaction->file_name).'"',
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

    private function privateDisk(): string
    {
        return config('filesystems.default', 'local');
    }
}
