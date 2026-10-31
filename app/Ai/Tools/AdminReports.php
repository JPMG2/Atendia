<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AskAdmin;
use App\Classes\Report\ListReport;
use App\Enums\ReportFormat;
use App\Interfaces\Main\AdminSkillTool;
use App\Services\Report\ReportMaker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The admin panel's reports as links: the same `reports.show` doors as each
 * screen's Imprimir / Excel / CSV buttons, and only the ones this person's
 * keys open. Nothing is attached; the report's own lock runs again on the click.
 */
class AdminReports implements AdminSkillTool
{
    /** The admin panel's list reports (the client's ones belong to a business, and the admin passes every gate). */
    public const array KEYS = [
        'negocios', 'adopcion', 'incidencias', 'radar', 'cumplimiento', 'cobranza', 'cobros', 'calidad-ia',
        'moderacion', 'testimonios', 'soporte', 'usuarios', 'auditoria', 'avisos', 'menciones', 'modelos-ia',
    ];

    /** @param  array<string, string>  $offered  report key => its title, only the ones this person may open */
    public function __construct(private readonly array $offered) {}

    public static function forAdmin(AskAdmin $assistant): ?static
    {
        $offered = [];

        foreach (self::KEYS as $key) {
            $report = app((string) config("atendia.reports.{$key}"));

            if ($report instanceof ListReport && $report->authorize($assistant->admin)) {
                $offered[$key] = $report->title;
            }
        }

        return $offered === [] ? null : new static($offered);
    }

    public function description(): Stringable|string
    {
        return 'Da el enlace a un reporte del panel de administración en PDF, Excel y CSV, el mismo de los botones '
            .'Imprimir / Excel / CSV de su pantalla, con lo que esa pantalla muestra hoy. No adjunta nada. Reportes: '
            .collect($this->offered)->map(fn (string $title, string $key): string => "{$key} ({$title})")->implode(', ').'.';
    }

    public function handle(Request $request): Stringable|string
    {
        $key = (string) $request->string('report');

        if (! array_key_exists($key, $this->offered)) {
            return 'No tengo ese reporte. Los que puedo dar: '.implode(', ', $this->offered).'.';
        }

        $links = collect(ReportFormat::cases())
            ->map(fn (ReportFormat $format): string => '['.__('ask_admin.report.formats.'.$format->value).']('.ReportMaker::link($key, $format).')')
            ->implode(' · ');

        return "Reporte «{$this->offered[$key]}»: {$links}. Dalos tal cual, con su formato de enlace.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'report' => $schema->string()->enum(array_keys($this->offered))->description('La clave del reporte')->required(),
        ];
    }
}
