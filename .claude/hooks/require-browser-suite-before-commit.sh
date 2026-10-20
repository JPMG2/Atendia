#!/usr/bin/env bash
#
# Hook PreToolUse (Bash): un commit que toca PANTALLAS exige haber corrido los
# tests de browser DE ESAS pantallas después del último cambio.
#
# Antes exigía la suite de browser entera (445 tests, ~40 min): el commit se
# volvió una hora de espera por cambios de una pantalla. Ahora se corre lo que
# el cambio toca, y la suite completa corre de noche (atendia-nightly-suite.sh).
#
# Qué exige:
#   - un test de browser tocado en el árbol: ese test;
#   - una vista de components/<carpeta>/<pantalla>: los tests de browser que
#     nombran esa carpeta (admin.roles) o cuyo archivo la nombra (Roles);
#   - css, js, layouts o componentes compartidos (ui, inputsform, catalog, site):
#     la lista corta de browser-smoke.txt, porque tocan todas las pantallas.
# Una pantalla sin ningún test que la nombre no exige nada: no hay qué correr.
#
# Qué cuenta como "corrió": block-browser-suite-reruns.sh anota cada archivo
# de browser que se corre sin --filter, y la suite entera anota la marca global.
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

command=$(jq -r '.tool_input.command // ""' 2>/dev/null)

case "$command" in
    *git\ commit*) ;;
    *) exit 0 ;;
esac

root="${ATENDIA_ROOT:-/var/www/atendia}"
full_marker="${TMPDIR:-/tmp}/atendia-browser-suite-ran"
ran_dir="${TMPDIR:-/tmp}/atendia-browser-ran"
smoke_file="$root/.claude/hooks/browser-smoke.txt"

# Lo que entraría al commit: el hook corre ANTES del `git add` del mismo comando,
# así que se mira el árbol entero contra HEAD, no solo lo stageado.
# quotepath=off: las pantallas se llaman ⚡nombre y git las escapa, y una ruta
# escapada no existe como archivo (el hook se la saltaba en silencio).
changed=$(git -C "$root" -c core.quotepath=off status --porcelain 2>/dev/null \
    | sed 's/^...//; s/.* -> //' \
    | grep -E '^(resources/(views|css|js)/|tests/Browser/.*\.php$)' || true)

[ -z "$changed" ] && exit 0

declare -A need=()

# Anota que `$1` (archivo de tests/Browser) tiene que haber corrido después de `$2`.
require() {
    [ -f "$root/tests/Browser/$1" ] || return 0
    [ "${need[$1]:-0}" -lt "$2" ] && need[$1]=$2
    return 0
}

require_smoke() {
    [ -f "$smoke_file" ] || return 0
    while IFS= read -r test; do
        [ -n "$test" ] && require "$test" "$1"
    done < "$smoke_file"
}

while IFS= read -r file; do
    [ -f "$root/$file" ] || continue
    mtime=$(stat -c %Y "$root/$file")

    case "$file" in
        tests/Browser/*.php)
            require "$(basename "$file")" "$mtime"
            ;;
        resources/views/components/*)
            rest="${file#resources/views/components/}"
            dir=$(dirname "$rest")
            first="${dir%%/*}"

            case "$first" in
                ui|inputsform|catalog|site)
                    require_smoke "$mtime"
                    continue
                    ;;
            esac

            key="${dir//\//.}"
            last="${dir##*/}"
            cap="$(tr '[:lower:]' '[:upper:]' <<< "${last:0:1}")${last:1}"

            # Una carpeta de una sola palabra ("roles") es demasiado común para
            # buscarla dentro de los tests: solo se compara contra el nombre del archivo.
            if [[ "$dir" == */* ]]; then
                while IFS= read -r hit; do
                    require "$(basename "$hit")" "$mtime"
                done < <(grep -lF -- "$key" "$root"/tests/Browser/*.php 2>/dev/null)
            fi

            for hit in "$root"/tests/Browser/*"$cap"*BrowserTest.php; do
                [ -f "$hit" ] && require "$(basename "$hit")" "$mtime"
            done
            ;;
        *)
            require_smoke "$mtime"
            ;;
    esac
done <<< "$changed"

[ "${#need[@]}" -eq 0 ] && exit 0

full_ran=$(stat -c %Y "$full_marker" 2>/dev/null || echo 0)
missing=()

for test in "${!need[@]}"; do
    ran=$(stat -c %Y "$ran_dir/$test" 2>/dev/null || echo 0)
    [ "$full_ran" -gt "$ran" ] && ran=$full_ran

    [ "$ran" -lt "${need[$test]}" ] && missing+=("tests/Browser/$test")
done

[ "${#missing[@]}" -eq 0 ] && exit 0

mapfile -t missing < <(printf '%s\n' "${missing[@]}" | sort)

cat >&2 <<MSG
BLOQUEADO: este commit toca pantallas y estos tests de browser no corrieron
después de esos cambios:

$(printf '  %s\n' "${missing[@]}")

Qué hacer AHORA (una sola corrida, con la salida a un archivo):
  docker exec -w /var/www/html -u www-data atendia-app sh -c 'timeout 1500 ./vendor/bin/pest --compact ${missing[*]} > /tmp/browser.log 2>&1; echo exit=\$?'

Lo que falle, re-correrlo solo con --filter: si pasa aislado es flake (se anota y se sigue).
La suite de browser entera no se exige: corre de noche y deja su informe en
storage/logs/nightly-suite/last.md.
MSG
exit 2
