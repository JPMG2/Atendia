#!/usr/bin/env bash
#
# Qué atrapó cada candado, y cuáles no atraparon nada. Es el dato que la regla
# "un candado que no atrapó nada real en 60 días es candidato a borrarse"
# necesitaba para dejar de ser una frase.
#
# Las dos capas de la receta de enforcement, por separado:
#   capa C · los hooks check-*.sh        (los anota run-checks.sh, PostToolUse)
#   capa B · los guardianes GoldenRules* (los anota enforce-turn-exit.sh, Stop)
#
# Imprime desde cuándo existe el registro: sin eso, "0 atrapadas en 60 días" se
# lee como 60 días de evidencia cuando puede ser el primer día, y se borrarían
# candados sanos (2026-10-03).
#
# Uso: .claude/hooks/catches.sh [días]   (por defecto 60)
#
set -u

dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
root="$(cd "$dir/../.." && pwd)"
log="$dir/state/catches.tsv"
days="${1:-60}"
since=$(date -u -d "-${days} days" +%Y-%m-%d)

printf 'Atrapadas desde %s (últimos %s días)\n' "$since" "$days"

if [ ! -s "$log" ]; then
    printf '\n  (el registro está vacío: todavía no hay ninguna atrapada)\n'
    printf '\n  OJO: sin registro no hay evidencia. No borres ningún candado con esto.\n'
    exit 0
fi

first=$(awk -F'\t' 'NR == 1 { print $1 }' "$log")
age=$(( ( $(date -u +%s) - $(date -u -d "$first" +%s) ) / 86400 ))

printf 'El registro arranca el %s: %s días de historia real.\n' "$first" "$age"

if [ "$age" -lt "$days" ]; then
    printf '\n  ⚠ Son MENOS de %s días. La lista de "sin atrapadas" todavía no alcanza\n' "$days"
    printf '    para borrar nada: faltan %s días de evidencia.\n' "$(( days - age ))"
fi

# Una atrapada de hook y una de guardián no se mezclan: miden capas distintas, y
# la de guardián llega marcada con el prefijo `guardian:`.
report() {
    printf '\n%s\n\n' "$1"

    local rows
    rows=$(awk -v since="$since" -v pat="$2" -F'\t' '
        $1 >= since && $2 ~ pat { total[$2]++; last[$2] = $1 }
        END { for (h in total) printf "  %-44s %4d   última: %s\n", h, total[h], last[h] }
    ' "$log" | sort -k2 -rn)

    printf '%s\n' "${rows:-  (ninguna)}"
}

report 'Capa C · hooks check-*.sh' '^check-'
report 'Capa B · guardianes Pest' '^guardian:'

# Los que no atraparon nada: se enumeran los candados que EXISTEN, no los que
# aparecen en el log, porque el candidato a borrarse es justamente el ausente.
printf '\nSin una sola atrapada en %s días (candidatos a borrarse):\n\n' "$days"

silent=0

quiet() {
    ! awk -v since="$since" -v name="$1" -F'\t' \
        '$1 >= since && $2 == name { found = 1 } END { exit found ? 0 : 1 }' "$log"
}

for check in "$dir"/check-*.sh; do
    name=$(basename "$check" .sh)

    if quiet "$name"; then
        printf '  capa C   %s\n' "$name"
        silent=$(( silent + 1 ))
    fi
done

for guardian in "$root"/tests/Feature/GoldenRules*.php "$root"/tests/Feature/BusinessIsolationTest.php; do
    [ -f "$guardian" ] || continue

    name=$(basename "$guardian" .php)

    if quiet "guardian:$name"; then
        printf '  capa B   %s\n' "$name"
        silent=$(( silent + 1 ))
    fi
done

[ "$silent" -eq 0 ] && printf '  (ninguno: todos atraparon algo)\n'

exit 0
