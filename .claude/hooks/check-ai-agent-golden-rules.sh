#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): un agente de app/Ai/Agents cumple el contrato
# y la economía de tokens en el momento de escribirlo (exit 2 si no).
#  - Todo agente declara #[Provider] y #[Model] (el costo se elige, no se hereda).
#  - Nadie arma su propio reloj ("Hoy es", "FECHA Y HORA", now()).
#  - Un agente Conversational usa AssistantContract (grounding + clock), con
#    el reloj ÚLTIMO en las instrucciones, y acota su memoria (MEMORY_LIMIT).
#
# Capa C. Las garantías permanentes (capa B): GoldenRulesAgentContractTest y
# GoldenRulesAgentEconomyTest — tocás uno, tocás el otro.
#
# Guías: .ai/guidelines/ia-contrato-asistentes.md · ia-economia-tokens.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

case "$file" in
    */app/Ai/Agents/*.php) ;;
    *) exit 0 ;;
esac

[ -f "$file" ] || exit 0

errors=""

grep -q '#\[Provider(' "$file" || errors="$errors\n- Falta #[Provider(...)]."
grep -q '#\[Model(' "$file" || errors="$errors\n- Falta #[Model(...)]: el modelo (y su costo) se declara explícito."

if grep -qE 'Hoy es |FECHA Y HORA|\bnow\(|CarbonImmutable::now' "$file"; then
    errors="$errors\n- Arma su propio reloj: la fecha sale de AssistantContract::for(...)->clock."
fi

if grep -qE 'implements[^{]*\bConversational\b' "$file"; then
    grep -q 'AssistantContract::for(' "$file" || errors="$errors\n- Conversational sin AssistantContract::for(...)."
    grep -qF '{$contract->grounding}' "$file" || errors="$errors\n- Falta {\$contract->grounding} en las instrucciones."
    tr '\n' ' ' < "$file" | grep -qE '\{\$contract->clock\}\s*INSTRUCCIONES;' \
        || errors="$errors\n- {\$contract->clock} tiene que ser lo ÚLTIMO de las instrucciones (caché de prompt)."
    grep -q 'MEMORY_LIMIT' "$file" || errors="$errors\n- Conversational sin MEMORY_LIMIT: la memoria se acota."
fi

if [ -n "$errors" ]; then
    printf "Regla de oro de agentes IA incumplida en %s:%b\nGuías: .ai/guidelines/ia-contrato-asistentes.md · .ai/guidelines/ia-economia-tokens.md\n" "$file" "$errors" >&2
    exit 2
fi

exit 0
