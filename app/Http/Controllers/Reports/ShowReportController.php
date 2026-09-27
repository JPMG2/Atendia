<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Enums\ReportFormat;
use App\Http\Controllers\Controller;
use App\Interfaces\Main\Report;
use App\Services\Report\ReportMaker;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one URL every Imprimir / Excel / CSV button points at. A plain GET, not
 * a Livewire action: "Imprimir" must open the PDF in a new tab to print it.
 */
class ShowReportController extends Controller
{
    public function __invoke(Request $request, string $report, string $format, ReportMaker $maker): Response
    {
        $class = config("atendia.reports.{$report}");
        $format = ReportFormat::tryFrom($format);

        abort_if(! is_string($class) || $format === null, 404);

        $source = app($class);
        abort_unless($source instanceof Report && $source->authorize($request->user()), 403);

        return $maker->respond($source->document, $format);
    }
}
