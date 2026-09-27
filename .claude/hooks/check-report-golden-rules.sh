#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): todo archivo generado (PDF, Excel, CSV) sale
# por la capa de reportes — un Report arma el contenido, un ReportExporter el
# formato, ReportMaker lo entrega — y el único botón es <x-ui.export-button>.
#
# Capa C de la receta. La garantía permanente es
# tests/Feature/GoldenRulesReportsTest.php (capa B): mismos patrones, si
# tocás uno, tocá el otro. Guía: .ai/guidelines/reportes.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

[ -f "$file" ] || exit 0

case "$file" in
    */app/Classes/Report/*|*/app/Services/Report/*) exit 0 ;;
    */app/*.php|*/resources/views/*.php|*/routes/*.php) ;;
    *) exit 0 ;;
esac

problems=""

grep -qP 'Barryvdh\\DomPDF|\bPdf::|PhpSpreadsheet\\Writer|IOFactory::createWriter|\bfputcsv\(|->streamDownload\(|->download\(' "$file" \
    && problems="${problems}- genera un archivo fuera de la capa de reportes: armá un Report (App\\\\Interfaces\\\\Main\\\\Report) y dejá el formato a ReportFormat\n"

case "$file" in
    */resources/views/components/ui/export-button.blade.php|*/routes/*) ;;
    *)
        grep -qF "'reports.show'" "$file" \
            && problems="${problems}- enlaza un reporte a mano: el único botón es <x-ui.export-button report=\"clave\" format=\"pdf|xlsx|csv\" />\n"
        ;;
esac

if [ -n "$problems" ]; then
    {
        printf 'Regla de oro incumplida en %s: los archivos salen por la capa de reportes.\n' "$file"
        printf '%b' "$problems"
        printf 'Guía: .ai/guidelines/reportes.md\n'
    } >&2
    exit 2
fi

exit 0
