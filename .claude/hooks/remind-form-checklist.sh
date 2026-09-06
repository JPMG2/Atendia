#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): recordatorio NO bloqueante. Cada vez que se
# escribe un blade con campos de formulario, inyecta el checklist de criterio
# (lo que ningún guardián puede verificar por patrón) como contexto, para que
# se recorra ANTES de dar la pantalla por terminada. Nació el 2026-09-06:
# el orden lógico estaba escrito y se incumplió igual.
#
set -u

payload=$(cat)
file=$(printf '%s' "$payload" | jq -r '.tool_input.file_path // ""' 2>/dev/null)

[ -f "$file" ] || exit 0

case "$file" in
    *.blade.php) ;;
    *) exit 0 ;;
esac

# La librería define los controles; el checklist aplica a quien los USA.
case "$file" in
    *components/inputsform/*|*components/ui/*) exit 0 ;;
esac

grep -qE '<x-(inputsform|ui)\.(input|select|combobox|textarea|file|phone|switch|checkbox)' "$file" || exit 0

context="Checklist de criterio de formularios (atendiadesign; correr ANTES de decir listo sobre ${file##*/var/www/atendia/}): 1) ORDEN LOGICO: identificador/nombre primero, luego descriptivo, formato, archivos, estado. 2) Copy via __() base neutra + es_AR si hay voseo. 3) Densidad: sin aire muerto, 20-24px de padding interno, hover en lo accionable. 4) Nada abreviado ni truncado; filas llegan al borde. 5) Responsive y claro/oscuro por tokens."

jq -n --arg ctx "$context" '{hookSpecificOutput: {hookEventName: "PostToolUse", additionalContext: $ctx}}'
exit 0
