#!/usr/bin/env bash
#
# Hook PreToolUse (Bash): BLOCKS `eas build` unless today's batch is written
# down in the app's register. Each store build waits ~50 min in the Free
# queue; on 2026-11-03 two points that both changed the app got one build
# each (AAB 5, then AAB 6) because nobody looked at the rest of the list first.
#
# Required in /var/www/aliadito-app/docs/aliadito-app.md, one line:
#   Tanda de build YYYY-MM-DD: entran <points>; quedan afuera <points> (<why>)
# with TODAY's date. `eas build:list|view|cancel` are reads and pass.
#
set -u

cmd=$(jq -r '.tool_input.command // ""' 2>/dev/null)

# Only where a command starts (line start or after ; & |), so a commit message that names it passes.
printf '%s' "$cmd" | grep -Eq '(^|[;&|])[[:space:]]*(timeout[[:space:]]+[0-9]+[[:space:]]+)?((npx|bunx)[[:space:]]+(-y[[:space:]]+)?)?eas(-cli)?(@[^[:space:]]+)?[[:space:]]+build([[:space:]]|$)' || exit 0

register=/var/www/aliadito-app/docs/aliadito-app.md
today=$(TZ=America/Argentina/Buenos_Aires date +%Y-%m-%d)

if grep -Eq "Tanda de build ${today}: entran .+; quedan afuera .+" "$register" 2>/dev/null; then
    exit 0
fi

log="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/state/catches.tsv"
printf '%s\t%s\t%s\n' "$(date -u +%Y-%m-%d)" 'require-build-batch' 'eas build' >> "$log" 2>/dev/null

cat >&2 <<EOF
🛑 BLOQUEADO: un build de la tienda sin su tanda escrita.

Antes de compilar, recorré la lista que queda (pendientes y registro de la app)
y juntá en ESTE build todo lo que toca la app. Escribí en $register:

  Tanda de build ${today}: entran <puntos>; quedan afuera <puntos> (<por qué>)

Un build por tanda, no uno por punto (2026-11-03: AAB 5 y 6 por dos puntos que iban juntos).
EOF
exit 2
