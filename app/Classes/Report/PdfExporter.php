<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Dto\ReportDto;
use App\Interfaces\Main\ReportExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;

/**
 * Every PDF (and every "Imprimir", which opens it to print). dompdf reads no
 * CSS variables, so the brand colours travel as data from here, never as
 * hex in the view.
 */
class PdfExporter implements ReportExporter
{
    /** @var array{brand: string, brandSoft: string, brandInk: string, ink: string, muted: string, line: string, zebra: string} */
    private const array PALETTE = [
        'brand' => '#0EA47A',
        'brandSoft' => '#E3F5EE',
        'brandInk' => '#08795A',
        'ink' => '#14201C',
        'muted' => '#5B6B66',
        'line' => '#DDE5E2',
        'zebra' => '#F4F8F6',
    ];

    public string $extension {
        get => 'pdf';
    }

    public string $mimeType {
        get => 'application/pdf';
    }

    public bool $opensInBrowser {
        get => true;
    }

    public function render(ReportDto $report, ReportLetterhead $letterhead): string
    {
        // Wide tables get the long side of the sheet: a column never wraps into a sliver.
        $pdf = Pdf::loadView('reports.pdf', [
            'report' => $report,
            'letterhead' => $letterhead,
            'palette' => self::PALETTE,
        ])->setPaper('a4', count($report->columns) > 6 ? 'landscape' : 'portrait');

        $pdf->render();
        $this->numberPages($pdf->getDomPDF());

        return $pdf->output();
    }

    /**
     * "Página X de Y": CSS counter(pages) prints 0 in dompdf, so the engine draws
     * the number on every page once the total is known, right-aligned to the
     * content edge (the 40px side margin is 30pt).
     */
    private function numberPages(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans');
        $size = 6.2;
        $width = $metrics->getTextWidth(__('reports.page', ['page' => '00', 'pages' => '00']), $font, $size);
        [$r, $g, $b] = sscanf(self::PALETTE['muted'], '#%02x%02x%02x');

        $canvas->page_text(
            $canvas->get_width() - 30 - $width,
            $canvas->get_height() - 37.6,
            __('reports.page', ['page' => '{PAGE_NUM}', 'pages' => '{PAGE_COUNT}']),
            $font,
            $size,
            [$r / 255, $g / 255, $b / 255],
        );
    }
}
