#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): REGLA DE ORO — fechas SIEMPRE con Flatpickr.
#
# Elegir una fecha o un rango se hace con <x-inputsform.datepicker> (Flatpickr
# con tokens de la casa). Prohibidos los inputs nativos de fecha/hora, las
# librerías viejas (daterangepicker/moment/pikaday) y el flatpickr por CDN.
#
# Es la capa C de la receta de enforcement. La garantía permanente es el test
# guardián tests/Feature/GoldenRulesDatepickerTest.php (capa B), que espeja
# esta misma búsqueda. Si tocás una, tocá la otra.
#
# Guía: .ai/guidelines/fechas.md
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

# Solo lo que llega al navegador: vistas Blade y JS del front.
case "$file" in
    *resources/views/*.blade.php) ;;
    *resources/js/*.js) ;;
    *) exit 0 ;;
esac

[ -f "$file" ] || exit 0

problems=""

if grep -Eiq 'type[[:space:]]*=[[:space:]]*["'"'"'](date|datetime-local|month|week|time)["'"'"']' "$file"; then
    problems="${problems}- Input nativo de fecha/hora (type=date|datetime-local|month|week|time). Usá <x-inputsform.datepicker>.\n"
fi

if grep -Eiq 'daterangepicker|moment(\.min)?\.js|pikaday|cdn\.jsdelivr\.net/npm/flatpickr' "$file"; then
    problems="${problems}- Librería de calendario ajena o flatpickr por CDN. Flatpickr entra por npm y solo vía <x-inputsform.datepicker>.\n"
fi

if [ -n "$problems" ]; then
    rel="${file##*resources/}"
    printf 'Regla de oro de FECHAS incumplida en resources/%s:\n%b\nGuía: .ai/guidelines/fechas.md\n' "$rel" "$problems" >&2
    exit 2
fi

exit 0
