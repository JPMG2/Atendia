<?php

declare(strict_types=1);

use App\Enums\ReportFormat;
use App\Interfaces\Main\Report;
use App\Interfaces\Main\ReportExporter;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Golden rule: every generated file leaves through the report layer
|--------------------------------------------------------------------------
| PDF, Excel or CSV: a Report builds the content, a ReportExporter the
| format, ReportMaker hands it out, and <x-ui.export-button> is the only
| button (owner's order, 2026-09-27). Born with zero offenders. Mirrored by
| the hook check-report-golden-rules.sh — touch one, touch the other.
*/

const REPORT_GENERATION_PATTERN = '/Barryvdh\\\\DomPDF|\bPdf::|PhpSpreadsheet\\\\Writer|IOFactory::createWriter|\bfputcsv\(|->streamDownload\(|->download\(/';

/** @return array<string, string> relative path => contents */
function reportScopedSources(): array
{
    return collect([...File::allFiles(app_path()), ...File::allFiles(resource_path('views')), ...File::allFiles(base_path('routes'))])
        ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.php'))
        ->mapWithKeys(fn ($file): array => [str_replace(base_path().'/', '', $file->getPathname()) => $file->getContents()])
        ->all();
}

test('no file is generated outside the report layer', function (): void {
    $offenders = collect(reportScopedSources())
        ->reject(fn (string $code, string $path): bool => str_starts_with($path, 'app/Classes/Report/') || str_starts_with($path, 'app/Services/Report/'))
        ->filter(fn (string $code): bool => preg_match(REPORT_GENERATION_PATTERN, $code) === 1)
        ->keys();

    expect($offenders->implode("\n"))->toBe('');
});

test('only the report layer builds a report link', function (): void {
    // ReportMaker::link is the one builder: the export button and the admin's assistant both ask it.
    $offenders = collect(reportScopedSources())
        ->reject(fn (string $code, string $path): bool => $path === 'app/Services/Report/ReportMaker.php' || str_starts_with($path, 'routes/'))
        ->filter(fn (string $code): bool => preg_match('/[\'"]reports\.show[\'"]|route\(\s*\\\\?(App\\\\Services\\\\Report\\\\)?ReportMaker::ROUTE/', $code) === 1)
        ->keys();

    expect($offenders->implode("\n"))->toBe('');
});

test('every exporter is a format and every report is registered', function (): void {
    $classes = collect(File::files(app_path('Classes/Report')))
        ->map(fn ($file): string => 'App\\Classes\\Report\\'.$file->getFilenameWithoutExtension());

    $formats = collect(ReportFormat::cases())->map(fn (ReportFormat $format): string => $format->exporter()::class);
    $registered = collect(config('atendia.reports'))->values();

    // An abstract base (ListReport) is a template for reports, not a report to register.
    $reports = $classes->filter(fn (string $class): bool => is_subclass_of($class, Report::class) && ! (new ReflectionClass($class))->isAbstract());

    expect($classes->filter(fn (string $class): bool => is_subclass_of($class, ReportExporter::class))->diff($formats)->values()->all())->toBe([])
        ->and($reports->diff($registered)->values()->all())->toBe([]);
});
