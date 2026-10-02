# Reportes — todo archivo generado sale por la capa de reportes (regla de oro)

> Orden de la dueña (2026-09-27): PDF, Excel, CSV o lo que venga se genera
> SOLO con estas clases, y el botón es uno solo. Enforcement de 3 capas en
> `reglas-de-oro-enforcement.md`.

Blindada: `tests/Feature/GoldenRulesReportsTest.php` + hook
`check-report-golden-rules.sh` (mismos patrones, tocar de a dos).

## Las piezas (el QUÉ separado del CÓMO)

- **`App\Interfaces\Main\Report`** — el QUÉ: `authorize(User)` (la cerradura
  va acá, no en ocultar el botón) + `$document` (property hook que arma el `ReportDto`). Uno por reporte,
  en `app/Classes/Report/`, registrado por clave en `config('atendia.reports')`.
- **`App\Dto\ReportDto`** — título, nombre de archivo, columnas, filas.
- **`App\Interfaces\Main\ReportExporter`** — el CÓMO: `$extension`,
  `$mimeType`, `$opensInBrowser` (property hooks) + `render(ReportDto)`.
  Hoy: `PdfExporter` (dompdf), `XlsxExporter` (PhpSpreadsheet), `CsvExporter`
  (`;` + BOM para Excel en español). Cada uno es un caso de `ReportFormat`.
- **`App\Services\Report\ReportMaker::respond()`** — la única puerta: nombre
  con fecha, `inline` para imprimir (PDF) o `attachment` para descargar.
- **Ruta única** `reports.show` (`/reportes/{clave}/{formato}`).
- **Botón único** `<x-ui.export-button report="clave" format="pdf|xlsx|csv" />`:
  color info suave, ícono por formato; el PDF abre en otra pestaña para imprimir.

## Cómo se suma un reporte

1. Clase en `app/Classes/Report/` que implementa `Report` (arma el `ReportDto`).
2. Clave en `config/atendia.php` → `reports`.
3. Los botones que hagan falta con `<x-ui.export-button>`.
4. Test Pest (contenido + cerradura). Nada más: los 3 formatos ya existen.

Un formato nuevo (p. ej. Word) = una clase `ReportExporter` + un caso en
`ReportFormat`, y sirve a TODOS los reportes.

## Checklist de salida

- [ ] Cero `Pdf::`, writers de PhpSpreadsheet, `fputcsv` o `->download(` fuera de la capa.
- [ ] El reporte autoriza en `authorize()`; registrado en `atendia.reports`.
- [ ] El botón es `<x-ui.export-button>`; ningún otro enlace a `reports.show`.
- [ ] `./vendor/bin/pest --filter="GoldenRulesReports|ReportsTest"` en verde.
