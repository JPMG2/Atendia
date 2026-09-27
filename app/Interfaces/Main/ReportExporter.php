<?php

declare(strict_types=1);

namespace App\Interfaces\Main;

use App\Dto\ReportDto;

/**
 * HOW a report becomes a file (PDF, Excel, CSV): one class per format that
 * serves every report. WHAT goes in it is a Report's job. Guarded by
 * GoldenRulesReportsTest: no file is generated anywhere else.
 */
interface ReportExporter
{
    public string $extension { get; }

    public string $mimeType { get; }

    /** Whether the browser should open it (print) instead of saving it. */
    public bool $opensInBrowser { get; }

    public function render(ReportDto $report): string;
}
