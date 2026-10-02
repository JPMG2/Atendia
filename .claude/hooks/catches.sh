#!/usr/bin/env bash
#
# Qué atrapó cada candado, y cuáles no atraparon nada. Es el dato que la regla
# "un candado que no atrapó nada real en 60 días es candidato a borrarse"
# necesitaba para dejar de ser una frase.
#
# Uso: .claude/hooks/catches.sh [días]   (por defecto 60)
#
set -u

dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
log="$dir/state/catches.tsv"
days="${1:-60}"
since=$(date -u -d "-${days} days" +%Y-%m-%d)

printf 'Atrapadas desde %s (últimos %s días)\n\n' "$since" "$days"

if [ ! -s "$log" ]; then
    echo "  (todavía no hay ninguna atrapada registrada)"
else
    awk -v since="$since" -F'\t' '$1 >= since { total[$2]++; last[$2] = $1 }
        END { for (h in total) printf "  %-44s %4d   última: %s\n", h, total[h], last[h] }' "$log" \
        | sort -k2 -rn
fi

printf '\nSin una sola atrapada en %s días (candidatos a borrarse):\n\n' "$days"

for check in "$dir"/check-*.sh; do
    name=$(basename "$check" .sh)

    if [ -s "$log" ] && awk -v since="$since" -v name="$name" -F'\t' \
        '$1 >= since && $2 == name { found = 1 } END { exit found ? 0 : 1 }' "$log"; then
        continue
    fi

    printf '  %s\n' "$name"
done
