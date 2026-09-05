#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): un Blade (template O el bloque PHP de un SFC
# de Livewire) jamás arma una query — pide el dato al MODELO por vocabulario
# de dominio (options(), suggestionsName(), serviceNames()...). Si el archivo
# recién escrito arma una, devuelve el error en el momento (exit 2).
#
# Es la capa C de la receta de enforcement. La garantía permanente es el test
# guardián tests/Feature/GoldenRulesBladeQueriesTest.php (capa B), que espeja
# este mismo allowlist y estos patrones. Si tocás uno, tocá el otro.
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

# Solo vistas Blade dentro de resources/views.
case "$file" in
    *resources/views/*.blade.php) ;;
    *) exit 0 ;;
esac

[ -f "$file" ] || exit 0

rel="${file##*resources/views/}"

# Deuda pre-existente congelada con su razón. NO agregar nada nuevo: arreglar.
query_allow="
components/⚡ws-demo.blade.php
"

in_list() { printf '%s\n' "$2" | grep -qxF "$1"; }

in_list "$rel" "$query_allow" && exit 0

# Puntos de entrada de query que un Blade no puede contener. Los verbos que
# comparte la Collection (->where, ->pluck) NO se prohíben: filtrar una lista
# en memoria en un template es presentación, no una query.
if grep -qE '::query\(|\bDB::|::(where[A-Za-z_]*|find|findOrFail|all|first|firstWhere|pluck|orderBy|latest|oldest)\(|->orderBy\(' "$file"; then
    {
        printf 'Regla de oro incumplida en %s: un Blade no arma queries.\n' "$rel"
        printf 'Mové la consulta a un método del MODELO con nombre de dominio\n'
        printf '(patrón options()/suggestionsName()) y llamalo desde el componente.\n'
        printf 'Guía: .ai/guidelines/queries-en-el-modelo.md\n'
    } >&2
    exit 2
fi

exit 0
