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
# Second lock, once the batch is written: the first thing EAS runs is `npm ci`, and
# it fails when package-lock.json is out of sync with package.json. This server's
# npm (11) rewrites lockfiles differently from the build image's (10.9.9), so on
# 2026-11-05 the vc 8 build died in "Install dependencies" after a 40 min queue.
# The check runs `npm ci --dry-run` with THAT npm on a clean copy of the two files.
#
set -u

cmd=$(jq -r '.tool_input.command // ""' 2>/dev/null)

# Only where a command starts (line start or after ; & |), so a commit message that names it passes.
printf '%s' "$cmd" | grep -Eq '(^|[;&|])[[:space:]]*(timeout[[:space:]]+[0-9]+[[:space:]]+)?((npx|bunx)[[:space:]]+(-y[[:space:]]+)?)?eas(-cli)?(@[^[:space:]]+)?[[:space:]]+build([[:space:]]|$)' || exit 0

app=${ALIADITO_APP_DIR:-/var/www/aliadito-app}
register="$app/docs/aliadito-app.md"
today=$(TZ=America/Argentina/Buenos_Aires date +%Y-%m-%d)
log="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/state/catches.tsv"

# The npm the build image ships: read it from the SPIN_UP_BUILDER lines of any EAS build log when it changes.
eas_npm='10.9.9'

# Passes silently, or blocks the build with what npm said.
check_lockfile() {
    local tmp out
    tmp=$(mktemp -d) || return 0
    cp "$app/package.json" "$app/package-lock.json" "$tmp/" 2>/dev/null
    [ -f "$app/.npmrc" ] && cp "$app/.npmrc" "$tmp/"

    if out=$(cd "$tmp" && timeout 50 npx -y "npm@${eas_npm}" ci --include=dev --ignore-scripts --dry-run 2>&1); then
        rm -rf "$tmp"
        return 0
    fi

    rm -rf "$tmp"
    printf '%s\t%s\t%s\n' "$(date -u +%Y-%m-%d)" 'require-build-batch' 'eas build (lockfile)' >> "$log" 2>/dev/null

    cat >&2 <<EOF
🛑 BLOQUEADO: el package-lock.json no instala con el npm de EAS (${eas_npm}).

$(printf '%s\n' "$out" | grep -E 'npm error|ERR!' | head -6)

Una compilación con este lock muere en «Install dependencies» tras ~40 min de cola
(2026-11-05, vc 8: «Missing: typescript@5.9.3 from lock file»). Lo normal: el lock lo
reescribió el npm de este servidor (11). Reconstruilo con el de EAS, en una copia, desde
el último lock bueno, y confirmá antes de compilar:

  npx -y npm@${eas_npm} install --package-lock-only --ignore-scripts
  npx -y npm@${eas_npm} ci --include=dev --ignore-scripts

(Si no hay salida de npm arriba, tardó más de 50 s o no hubo red: reintentá.)
EOF
    exit 2
}

if grep -Eq "Tanda de build ${today}: entran .+; quedan afuera .+" "$register" 2>/dev/null; then
    check_lockfile
    exit 0
fi

printf '%s\t%s\t%s\n' "$(date -u +%Y-%m-%d)" 'require-build-batch' 'eas build' >> "$log" 2>/dev/null

cat >&2 <<EOF
🛑 BLOQUEADO: un build de la tienda sin su tanda escrita.

Antes de compilar, recorré la lista que queda (pendientes y registro de la app)
y juntá en ESTE build todo lo que toca la app. Escribí en $register:

  Tanda de build ${today}: entran <puntos>; quedan afuera <puntos> (<por qué>)

Un build por tanda, no uno por punto (2026-11-03: AAB 5 y 6 por dos puntos que iban juntos).
EOF
exit 2
