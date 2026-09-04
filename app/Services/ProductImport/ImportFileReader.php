<?php

declare(strict_types=1);

namespace App\Services\ProductImport;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads the shape of an uploaded price list: the headers, a handful of sample
 * rows and the total count. It never loads the whole sheet — the mapper only
 * needs a taste, the full read belongs to the queued import job.
 */
class ImportFileReader
{
    public const int SAMPLE_ROWS = 5;

    /**
     * @return array{headers: list<string>, samples: list<list<string>>, total_rows: int}
     */
    public function read(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $sheet = $reader->load($path)->getSheet(0);

        $lastRow = $sheet->getHighestDataRow();
        $lastColumn = $sheet->getHighestDataColumn();

        $sampleEnd = min($lastRow, 1 + self::SAMPLE_ROWS);
        $rows = $sheet->rangeToArray("A1:{$lastColumn}{$sampleEnd}", '', false, false);

        $headers = collect(array_shift($rows) ?? [])
            ->map(fn ($cell): string => trim((string) $cell));

        // Trailing blank columns are spreadsheet noise, not data.
        while ($headers->isNotEmpty() && $headers->last() === '') {
            $headers->pop();
        }

        $width = $headers->count();

        $samples = collect($rows)
            ->map(fn (array $row): array => array_map(
                fn ($cell): string => trim((string) $cell),
                array_slice($row, 0, $width),
            ))
            ->filter(fn (array $row): bool => implode('', $row) !== '')
            ->values()
            ->all();

        return [
            'headers' => $headers->values()->all(),
            'samples' => $samples,
            'total_rows' => max(0, $lastRow - 1),
        ];
    }

    /**
     * Every data row, trimmed to the header width. This one does load the
     * whole sheet — it only ever runs inside the queued import job.
     *
     * @return list<list<string>>
     */
    public function rows(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $sheet = $reader->load($path)->getSheet(0);

        $rows = $sheet->toArray(null, false, false, false);

        $width = count($rows[0] ?? []);

        array_shift($rows);

        return collect($rows)
            ->map(fn (array $row): array => array_map(
                fn ($cell): string => trim((string) $cell),
                array_slice(array_pad($row, $width, ''), 0, $width),
            ))
            ->filter(fn (array $row): bool => implode('', $row) !== '')
            ->values()
            ->all();
    }
}
