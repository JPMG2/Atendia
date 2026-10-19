<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Dto\ReportDto;
use App\Interfaces\Main\ReportExporter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Every Excel file: the letterhead on top, then a table with frozen titles, filters and sized columns. */
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

    /** Where the table starts: five lines of letterhead and one blank above it. */
    private const int HEADER_ROW = 7;

    private const string BRAND = 'FF0EA47A';

    private const string BRAND_SOFT = 'FFE3F5EE';

    private const string INK = 'FF14201C';

    private const string MUTED = 'FF5B6B66';

    public function render(ReportDto $report, ReportLetterhead $letterhead): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheetTitle($report->title));
        $columns = max(1, count($report->columns));
        $last = Coordinate::stringFromColumnIndex($columns);

        $this->letterhead($sheet, $report, $letterhead, $columns, $last);
        $this->table($sheet, $report, $columns, $last);

        $sheet->getPageSetup()->setOrientation($columns > 6 ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getSheetView()->setZoomScale(100);

        $path = (string) tempnam(sys_get_temp_dir(), 'report');
        (new Xlsx($spreadsheet))->save($path);
        $bytes = (string) file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    /** Aliadito and its slogan on a jade band, then the title, whose it is, the filters and the moment. */
    private function letterhead(Worksheet $sheet, ReportDto $report, ReportLetterhead $letterhead, int $columns, string $last): void
    {
        $sheet->setCellValue('A1', trim($letterhead->brand.'  ·  '.$letterhead->tagline, ' ·'));
        $sheet->getStyle("A1:{$last}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::BRAND]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->setCellValue('A2', $report->title);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(15)->getColor()->setARGB(self::INK);

        $lines = collect($letterhead->subjectLines)->map(fn (array $line): string => $line['label'].' '.$line['value'])->implode('  ·  ');
        $sheet->setCellValue('A3', trim($letterhead->subjectLabel.': '.$letterhead->subjectName.($lines === '' ? '' : '  ·  '.$lines)));

        $filters = collect($report->filters)->map(fn (array $filter): string => $filter['label'].' = '.$filter['value'])->implode(', ');
        $count = $report->total !== null && $report->isFiltered
            ? __('reports.scope.of', ['shown' => count($report->rows), 'total' => $report->total])
            : (string) count($report->rows);
        $sheet->setCellValue('A4', __('reports.scope.title').': '.($filters === '' ? __('reports.scope.none') : $filters).'  ·  '.$count);

        $sheet->setCellValue('A5', __('reports.generated', ['date' => $letterhead->generatedAt]).'  ·  '.$letterhead->timezoneLabel);
        $sheet->getStyle('A5')->getFont()->getColor()->setARGB(self::MUTED);
    }

    /** Titles frozen and filtered, numbers as numbers and everything else as text, so a leading zero survives. */
    private function table(Worksheet $sheet, ReportDto $report, int $columns, string $last): void
    {
        $head = self::HEADER_ROW;

        foreach (array_values($report->columns) as $index => $title) {
            $sheet->setCellValue([$index + 1, $head], $title);
        }

        $sheet->getStyle("A{$head}:{$last}{$head}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => self::INK]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::BRAND_SOFT]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => self::BRAND]]],
        ]);

        foreach (array_values($report->rows) as $rowIndex => $row) {
            foreach (array_values($row) as $columnIndex => $value) {
                $cell = [$columnIndex + 1, $head + 1 + $rowIndex];

                is_int($value) || is_float($value)
                    ? $sheet->setCellValue($cell, $value)
                    : $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
            }
        }

        $sheet->freezePane('A'.($head + 1));
        $sheet->setAutoFilter("A{$head}:{$last}".($head + max(1, count($report->rows))));

        foreach (range(1, $columns) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }
    }

    /** Excel refuses : \ / ? * [ ] in a sheet name and caps it at 31 characters. */
    private function sheetTitle(string $title): string
    {
        return mb_substr((string) preg_replace('/[:\\\\\/?*\[\]]/', ' ', $title), 0, 31);
    }
}
