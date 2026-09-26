#!/usr/bin/env bash
#
# Hook UserPromptSubmit: con CADA mensaje de la dueña se reinyectan las reglas
# de trabajo de no-mediocre.md. Escritas en una guía quedaban enterradas en
# ~68 KB de CLAUDE.md y se incumplían al final de las tareas largas.
#
# Además marca el INICIO del turno: el hook Stop (enforce-turn-exit.sh) usa esa
# marca para saber qué archivos se tocaron en este turno.
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

payload=$(cat)
session=$(printf '%s' "$payload" | jq -r '.session_id // "none"' 2>/dev/null)
prompt=$(printf '%s' "$payload" | jq -r '.prompt // ""' 2>/dev/null)

marker="${TMPDIR:-/tmp}/atendia-turn-${session}"

# Los avisos internos (subagentes, tareas de fondo) también llegan como prompt:
# si movieran la marca, lo tocado antes del aviso se escaparía de la puerta.
if ! printf '%s' "$prompt" | grep -qE '<task-notification>|<agent-message|SYSTEM NOTIFICATION'; then
    touch "$marker"
    rm -f "${marker}.blocks"
fi

cat <<'RULES'
REGLAS DE TRABAJO (no-mediocre.md — se cumplen en ESTE turno):
1. Construir EXACTAMENTE lo pedido. Nada de features, estados o "mejoras" no pedidas.
2. Una mejora se ofrece en UNA línea y decide ella. Toda tarea de UI cierra con 2–3 mejoras investigadas.
3. Antes de construir algo nuevo: repetir la spec en una línea.
4. Antes de decir "listo": verificación VISUAL real. Un test verde no alcanza.
5. Orden directa = ejecutar. No repreguntar lo ya decidido.
6. Seguir el patrón que el proyecto ya usa; no inventar uno paralelo.
7. Sin excusas: si algo salió mal, se asume y se corrige.
Al cerrar con código tocado, el hook Stop corre los guardianes GoldenRules* y, si hubo vistas, exige en la respuesta los bloques "Checklist de salida", "Verificación visual" y "Mejoras para decidir".
Si se toca app/Ai: checklist de .ai/guidelines/ia-economia-tokens.md.
RULES

# Ratchet: si el mensaje reclama una regla rota, esa regla gana su control
# automático ANTES de seguir con cualquier otra cosa.
if printf '%s' "$prompt" | grep -qiE 'regla(s)? de oro|rompiste|incumpl|no cumpl|(otra vez|de nuevo) (lo mismo|rompiste|te olvidaste|no)|te (la|las) pasaste|te salteaste|te olvidaste'; then
    cat <<'RATCHET'
RATCHET: este mensaje parece reclamar una regla incumplida. ANTES de seguir con la tarea:
identificar la regla, sumarle su control automático (test guardián y/o hook, receta en
reglas-de-oro-enforcement.md) y recién después continuar. Una regla solo escrita no alcanza.
RATCHET
fi

exit 0
