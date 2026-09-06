#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): la tajada VERIFICABLE del "orden lógico de
# campos" (formularios.md §5.7): si un blade tiene el campo name="name" y
# también un drop de archivo o un textarea, el nombre va PRIMERO. Nació el
# 2026-09-06: la tarjeta Identidad salió con el logo arriba del nombre.
#
# Es la capa C de la receta de enforcement. La garantía permanente es el test
# guardián tests/Feature/GoldenRulesFieldOrderTest.php (capa B), que espeja
# esta misma regla. Si tocás una, tocá la otra.
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

[ -f "$file" ] || exit 0

case "$file" in
    *.blade.php) ;;
    *) exit 0 ;;
esac

# La librería de componentes define los controles, no un formulario.
case "$file" in
    *components/inputsform/*|*components/ui/*) exit 0 ;;
esac

off_name=$(grep -bo 'name="name"' "$file" 2>/dev/null | head -1 | cut -d: -f1)
[ -n "$off_name" ] || exit 0

off_media=$(grep -boE '<x-(inputsform\.(file|textarea)|ui\.textarea)' "$file" 2>/dev/null | head -1 | cut -d: -f1)
[ -n "$off_media" ] || exit 0

if [ "$off_name" -gt "$off_media" ]; then
    echo "Orden lógico de campos roto en ${file##*/var/www/atendia/}:" >&2
    echo "- El campo name=\"name\" aparece DESPUÉS de un drop de archivo o textarea." >&2
    echo "  El nombre identifica el registro: va primero (formularios.md §5.7)." >&2
    exit 2
fi

exit 0
