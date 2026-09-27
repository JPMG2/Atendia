<?php

declare(strict_types=1);

namespace App\Enums;

use App\Classes\Report\CsvExporter;
use App\Classes\Report\PdfExporter;
use App\Classes\Report\XlsxExporter;
use App\Interfaces\Main\ReportExporter;

enum ReportFormat: string
{
    case Pdf = 'pdf';
    case Xlsx = 'xlsx';
    case Csv = 'csv';

    public function exporter(): ReportExporter
    {
        return app(match ($this) {
            self::Pdf => PdfExporter::class,
            self::Xlsx => XlsxExporter::class,
            self::Csv => CsvExporter::class,
        });
    }
}
