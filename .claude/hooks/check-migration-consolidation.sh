#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): bloquea escribir una migración add_/drop_/
# rename_ nueva. Convención del usuario (2026-09-02): mientras no haya go-live,
# una columna nueva se suma REDISEÑANDO la migración de CREACIÓN de la tabla
# (atendia se sincroniza con un ALTER quirúrgico; atendia_testing se rearma
# sola con RefreshDatabase). Las add_ anteriores a la fecha son historia.
#
# Incumplida el 2026-09-03 pese a estar escrita — por eso la capa determinística
# (misma regla en tests/Feature/GoldenRulesMigrationsTest.php).
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

case "$file" in
    */database/migrations/*.php) ;;
    *) exit 0 ;;
esac

base=$(basename "$file")
stamp=${base:0:10}

case "$stamp" in
    [0-9][0-9][0-9][0-9]_[0-9][0-9]_[0-9][0-9]) ;;
    *) exit 0 ;;
esac

if [[ ! "$stamp" > "2026_09_02" ]]; then
    exit 0
fi

if [[ "$base" =~ _(add|drop|rename)_.+_(to|from|on|in)_.+_table\.php$ ]]; then
    cat >&2 << 'EOF'
BLOQUEADO: migración add_/drop_/rename_ nueva.

Regla de oro (2026-09-02): la columna va DENTRO de la migración de CREACIÓN
de la tabla — se rediseña esa migración, no se apilan parches.

Qué hacer AHORA:
  1. Sumar la columna a la migración create_*_table existente.
  2. Sincronizar atendia con un ALTER quirúrgico (Schema::table en tinker).
  3. atendia_testing se rearma sola vía RefreshDatabase (correr los tests).
EOF
    exit 2
fi

exit 0
