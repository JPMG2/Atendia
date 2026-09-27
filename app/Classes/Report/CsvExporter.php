<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Dto\ReportDto;
use App\Interfaces\Main\ReportExporter;

/**
 * Every CSV. Semicolon and a UTF-8 BOM on purpose: Spanish-locale Excel
 * reads the comma as the decimal mark and mangles accents without the BOM.
 */
class CsvExporter implements ReportExporter
{
    public string $extension {
        get => 'csv';
    }

    public string $mimeType {
        get => 'text/csv; charset=UTF-8';
    }

    public bool $opensInBrowser {
        get => false;
    }

    public function render(ReportDto $report): string
    {
        $stream = fopen('php://temp', 'r+');

        foreach ([$report->columns, ...$report->rows] as $row) {
            fputcsv($stream, $row, ';', '"', '');
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return "\u{FEFF}".$csv;
    }
}
