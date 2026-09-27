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

test('only the export button links a report', function (): void {
    $offenders = collect(reportScopedSources())
        ->reject(fn (string $code, string $path): bool => $path === 'resources/views/components/ui/export-button.blade.php' || str_starts_with($path, 'routes/'))
        ->filter(fn (string $code): bool => str_contains($code, "'reports.show'"))
        ->keys();

    expect($offenders->implode("\n"))->toBe('');
});

test('every exporter is a format and every report is registered', function (): void {
    $classes = collect(File::files(app_path('Classes/Report')))
        ->map(fn ($file): string => 'App\\Classes\\Report\\'.$file->getFilenameWithoutExtension());

    $formats = collect(ReportFormat::cases())->map(fn (ReportFormat $format): string => $format->exporter()::class);
    $registered = collect(config('atendia.reports'))->values();

    expect($classes->filter(fn (string $class): bool => is_subclass_of($class, ReportExporter::class))->diff($formats)->values()->all())->toBe([])
        ->and($classes->filter(fn (string $class): bool => is_subclass_of($class, Report::class))->diff($registered)->values()->all())->toBe([]);
});
