<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

class OperationalContextExtractor
{
    public function extract(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $text = match ($extension) {
            'pdf' => $this->extractPdf($file),
            'md', 'markdown', 'txt' => file_get_contents($file->getRealPath()) ?: '',
            default => '',
        };

        return $this->normalize($text);
    }

    private function extractPdf(UploadedFile $file): string
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($file->getRealPath());

        return $pdf->getText();
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim(Str::limit($text, 120000, "\n\n[Contenido truncado por longitud]"));
    }
}
