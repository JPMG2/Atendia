# Reportes — PDF, Excel y CSV por una sola capa

- Nada de `Pdf::`, writers de PhpSpreadsheet, `fputcsv` ni `->download(` fuera de la capa.
- El QUÉ: una clase en `app/Classes/Report/` que implementa `App\Interfaces\Main\Report`
  (`authorize(User)` — la cerradura va acá — y `$document`, que arma el `ReportDto`),
  registrada por clave en `config('atendia.reports')`.
- El CÓMO: `App\Interfaces\Main\ReportExporter` (`PdfExporter`, `XlsxExporter`, `CsvExporter`),
  un caso por formato en `ReportFormat`. Un formato nuevo sirve a TODOS los reportes.
- La única puerta de salida es `ReportMaker::respond()`; la única ruta, `reports.show`.
- El botón es `<x-ui.export-button report="clave" format="pdf|xlsx|csv" />`; ningún otro
  enlace a `reports.show`.
- Reporte nuevo = clase + clave en config + botones + test Pest (contenido y cerradura).

Candados: `GoldenRulesReportsTest` + `check-report-golden-rules.sh`.
