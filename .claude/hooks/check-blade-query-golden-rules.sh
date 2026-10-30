#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): un Blade, un Form de Livewire o un controlador
# jamás arma una query — pide el dato al MODELO por vocabulario de dominio
# (options(), forEditing(), editable()...). Si el archivo recién escrito arma
# una, devuelve el error en el momento (exit 2).
#
# Es la capa C. La garantía permanente es tests/Feature/GoldenRulesBladeQueriesTest.php
# (capa B). Las dos comparten el MISMO scanner (tests/Support/QueryScanner.php),
# con su allowlist y sus patrones, así que no pueden divergir.
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

# La ruta puede llegar desde el host o desde dentro del contenedor.
rel="${file#/var/www/atendia/}"
rel="${rel#/var/www/html/}"
file="/var/www/atendia/$rel"

case "$rel" in
    resources/views/*.blade.php|app/Livewire/Forms/*.php|app/Http/Controllers/*.php|app/Http/Requests/*.php) target="$rel" ;;
    # Un modelo decide qué es vocabulario: al tocarlo se juzga todo, como el guardián.
    app/Models/*.php|app/Traits/*.php) target="--all" ;;
    *) exit 0 ;;
esac

[ -f "$file" ] || exit 0

output=$(docker exec -w /var/www/html atendia-app php tests/Support/query_check.php "$target" 2>&1)

if [ $? -ne 0 ]; then
    echo "$output" >&2
    exit 2
fi

exit 0
