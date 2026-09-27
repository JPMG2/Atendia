<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Dto\ReportDto;
use App\Interfaces\Main\ReportExporter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Every Excel file: bold header row, columns sized to their content. */
class XlsxExporter implements ReportExporter
{
    public string $extension {
        get => 'xlsx';
    }

    public string $mimeType {
        get => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public bool $opensInBrowser {
        get => false;
    }

    public function render(ReportDto $report): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($report->title, 0, 31));
        $sheet->fromArray([$report->columns, ...$report->rows]);
        $sheet->getStyle([1, 1, max(1, count($report->columns)), 1])->getFont()->setBold(true);

        foreach (range(1, max(1, count($report->columns))) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'report');
        (new Xlsx($spreadsheet))->save($path);
        $bytes = (string) file_get_contents($path);
        unlink($path);

        return $bytes;
    }
}
