<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CsvImport
{
    public static function contentsFromRequest(Request $request, string $fileKey = 'csv_file'): ?string
    {
        if ($request->hasFile($fileKey)) {
            $file = $request->file($fileKey);
            $extension = Str::lower($file->getClientOriginalExtension());

            if (in_array($extension, ['xlsx', 'xls', 'ods'], true)) {
                return self::spreadsheetContents($file->getRealPath());
            }

            return file_get_contents($file->getRealPath()) ?: null;
        }

        return null;
    }

    public static function download(string $filename, array $rows)
    {
        $headers = empty($rows) ? ['sin_datos'] : array_keys($rows[0]);

        $callback = function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($header) => $row[$header] ?? '', $headers));
            }

            fclose($handle);
        };

        return Response::streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public static function downloadSpreadsheet(string $filename, array $rows)
    {
        $headers = empty($rows) ? ['sin_datos'] : array_keys($rows[0]);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($headers as $columnIndex => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 1).'1', $header);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($headers as $columnIndex => $header) {
                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 2),
                    $row[$header] ?? ''
                );
            }
        }

        $callback = function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        };

        return Response::streamDownload($callback, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function rows(string $contents): array
    {
        $contents = trim($contents);

        if ($contents === '') {
            return [];
        }

        $delimiter = self::detectDelimiter($contents);
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $headers = null;
        $rows = [];

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($data === [null] || ! collect($data)->filter(fn ($value) => trim((string) $value) !== '')->isNotEmpty()) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(fn ($header) => self::normalizeKey((string) $header), $data);
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = trim((string) ($data[$index] ?? ''));
            }

            if (! empty(array_filter($row, fn ($value) => $value !== ''))) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    public static function value(array $row, array $keys, ?string $default = null): ?string
    {
        foreach ($keys as $key) {
            $normalized = self::normalizeKey($key);

            if (array_key_exists($normalized, $row) && trim((string) $row[$normalized]) !== '') {
                return trim((string) $row[$normalized]);
            }
        }

        return $default;
    }

    public static function normalizeKey(string $key): string
    {
        return Str::of($key)
            ->lower()
            ->ascii()
            ->replaceMatches('/\s+/', '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->toString();
    }

    private static function detectDelimiter(string $contents): string
    {
        $firstLine = Str::of($contents)->before("\n")->toString();

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    private static function spreadsheetContents(string $path): string
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('No se pudo leer el archivo Excel. Verifica que sea un XLSX/XLS válido.');
        }

        $sheet = $spreadsheet->getSheet(0);
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $handle = fopen('php://temp', 'r+');

        for ($row = 1; $row <= $highestRow; $row++) {
            $values = [];
            $hasValue = false;

            for ($column = 1; $column <= $highestColumn; $column++) {
                $coordinate = Coordinate::stringFromColumnIndex($column).$row;
                $value = trim((string) $sheet->getCell($coordinate)->getFormattedValue());
                $values[] = $value;
                $hasValue = $hasValue || $value !== '';
            }

            if ($hasValue) {
                fputcsv($handle, $values);
            }
        }

        $spreadsheet->disconnectWorksheets();
        rewind($handle);
        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $contents;
    }
}
