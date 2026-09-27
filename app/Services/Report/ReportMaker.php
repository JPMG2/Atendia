<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Dto\ReportDto;
use App\Enums\ReportFormat;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one door a generated file leaves through: picks the format's exporter,
 * names the file with its date and hands it to the browser — opened (print)
 * or saved. Guarded by GoldenRulesReportsTest.
 */
class ReportMaker
{
    public function respond(ReportDto $report, ReportFormat $format): Response
    {
        $exporter = $format->exporter();
        $filename = Str::slug($report->filename).'-'.now()->inBusinessTime()->format('Y-m-d').'.'.$exporter->extension;
        $disposition = $exporter->opensInBrowser ? 'inline' : 'attachment';

        return response($exporter->render($report), 200, [
            'Content-Type' => $exporter->mimeType,
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
        ]);
    }
}
