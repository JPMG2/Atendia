#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): valida la regla de oro de CONTROLES VIVOS sobre
# la vista recién escrita y, si dibuja un botón que no corre nada, devuelve el
# error en el momento (exit 2) para corregir antes de correr los tests.
#
# Es la capa C. La garantía permanente es
# tests/Feature/GoldenRulesLiveControlsTest.php (capa B). Las dos comparten el
# MISMO scanner (tests/Support/ControlScanner.php), así que no pueden divergir.
#
# Regla: .ai/guidelines/controles-vivos.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

[ -n "$file" ] || exit 0

case "$file" in
    */resources/views/*.blade.php) ;;
    *) exit 0 ;;
esac

root="/var/www/atendia"
rel="${file#"$root"/}"

[ -f "$file" ] || exit 0

output=$(docker exec -w /var/www/html atendia-app php tests/Support/control_check.php "$rel" 2>&1)

if [ $? -ne 0 ]; then
    echo "$output" >&2
    exit 2
fi

exit 0
