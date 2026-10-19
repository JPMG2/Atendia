<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Classes\Report\ReportLetterhead;
use App\Dto\ReportDto;
use App\Enums\ReportAudience;
use App\Enums\ReportFormat;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one door a generated file leaves through: picks the format's exporter,
 * dresses the document with its letterhead, names the file and hands it to the
 * browser — opened (print) or saved. Guarded by GoldenRulesReportsTest.
 */
class ReportMaker
{
    public function respond(ReportDto $report, ReportFormat $format): Response
    {
        $exporter = $format->exporter();
        $letterhead = ReportLetterhead::for($report, auth()->user());
        $disposition = $exporter->opensInBrowser ? 'inline' : 'attachment';

        return response($exporter->render($report, $letterhead), 200, [
            'Content-Type' => $exporter->mimeType,
            'Content-Disposition' => $disposition.'; filename="'.$this->filename($report, $letterhead).'.'.$exporter->extension.'"',
        ]);
    }

    /**
     * "Clientes_Laboratorio-Vida_2026-10-19_filtrado": what it is, whose it is, when,
     * and whether it is a cut of the list, so a file found a month later says so.
     */
    private function filename(ReportDto $report, ReportLetterhead $letterhead): string
    {
        return collect([
            Str::ucfirst(Str::slug($report->filename)),
            $report->audience === ReportAudience::Client ? Str::slug($letterhead->subjectName) : null,
            now()->inBusinessTime()->format('Y-m-d'),
            $report->isFiltered ? 'filtrado' : null,
        ])->filter()->implode('_');
    }
}
