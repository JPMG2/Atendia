#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): el aislamiento por tenant vive en el trait
# BelongsToBusiness (global scope + sello al crear). NADIE apaga el scope:
# un `withoutGlobalScope` en app/ o en una vista es la puerta a leer datos
# de otro negocio, y se rechaza en el momento (exit 2).
#
# Es la capa C de la receta de enforcement. La garantía permanente es
# tests/Feature/GoldenRulesTenancyTest.php (capa B): trait obligatorio por
# introspección del esquema + este mismo patrón. Si tocás uno, tocá el otro.
# Sin allowlist: nació en cero (2026-09-09) y en cero se queda.
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md · guía: tenancy.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

# Solo código de la app y vistas; los tests pueden nombrar el patrón.
case "$file" in
    *tests/*) exit 0 ;;
    *app/*.php) ;;
    *resources/views/*.blade.php) ;;
    *) exit 0 ;;
esac

[ -f "$file" ] || exit 0

if grep -q 'withoutGlobalScope' "$file"; then
    {
        printf 'Regla de oro incumplida en %s: el scope de tenant no se apaga.\n' "$file"
        printf 'El aislamiento por negocio vive en BelongsToBusiness; para correr\n'
        printf 'en contexto de otro negocio (jobs, consola) usá Tenant::for($id, fn).\n'
        printf 'Guía: .ai/guidelines/tenancy.md\n'
    } >&2
    exit 2
fi

exit 0
