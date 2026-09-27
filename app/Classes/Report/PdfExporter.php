<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Dto\ReportDto;
use App\Interfaces\Main\ReportExporter;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Every PDF (and every "Imprimir", which opens it to print). dompdf reads no
 * CSS variables, so the brand colours travel as data from here, never as
 * hex in the view.
 */
class PdfExporter implements ReportExporter
{
    /** @var array{brand: string, ink: string, muted: string, line: string, zebra: string} */
    private const array PALETTE = [
        'brand' => '#0EA47A',
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

    public function render(ReportDto $report): string
    {
        return Pdf::loadView('reports.pdf', [
            'report' => $report,
            'palette' => self::PALETTE,
            'generatedAt' => now()->inBusinessTime()->format('d/m/Y H:i'),
        ])->output();
    }
}
