#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): corre TODOS los check-*.sh sobre el archivo
# recién escrito, anota lo que cada uno atrapa y devuelve los errores juntos.
#
# Nació el 2026-10-02, consolidando 18 entradas de settings.json en una:
#   1. Nada contaba lo que atrapaba un candado, así que la regla "un candado
#      que no atrapó nada real en 60 días se borra" no se podía aplicar.
#      Ahora cada atrapada deja una línea en state/catches.tsv.
#   2. Un check nuevo entra DEJANDO EL ARCHIVO en esta carpeta: no hay que
#      acordarse de registrarlo en settings.json.
#   3. El primer candado en rojo ya no tapa a los demás: salen todos.
#
# Resumen: .claude/hooks/catches.sh
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

payload=$(cat)
file=$(jq -r '.tool_input.file_path // ""' <<<"$payload" 2>/dev/null)

[ -n "$file" ] || exit 0

dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
state="$dir/state"
mkdir -p "$state"

failed=0
report=""

for check in "$dir"/check-*.sh; do
    [ -f "$check" ] || continue

    out=$(printf '%s' "$payload" | bash "$check" 2>&1)

    if [ "$?" -eq 0 ]; then
        continue
    fi

    failed=1
    report="${report}${out}"$'\n'
    printf '%s\t%s\t%s\n' \
        "$(date -u +%Y-%m-%d)" \
        "$(basename "$check" .sh)" \
        "${file#/var/www/atendia/}" >> "$state/catches.tsv"
done

if [ "$failed" -eq 1 ]; then
    printf '%s' "$report" >&2
    exit 2
fi

exit 0
