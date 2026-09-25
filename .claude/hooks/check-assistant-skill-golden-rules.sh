#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): todo lo que la IA pueda manejar es un skill.
# Una herramienta en app/Ai/Tools que no implementa AssistantSkillTool (cliente)
# ni OwnerSkillTool (dueña, Pregúntale a AtendIa) o que
# no está en config/atendia.php (assistant.skills), o un agente que hace
# `new` de una herramienta, se rechaza en el momento (exit 2).
#
# Capa C de la receta. La garantía permanente es
# tests/Feature/GoldenRulesAssistantSkillsTest.php (capa B), que además
# compara el config con el seeder. Si tocás uno, tocá el otro.
#
# Guía: .ai/guidelines/skills-del-asistente.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)
root=/var/www/atendia

[ -f "$file" ] || exit 0

case "$file" in
    */app/Ai/Tools/*.php)
        class=$(basename "$file" .php)
        problems=""
        grep -qE 'implements[^{]*(AssistantSkillTool|OwnerSkillTool)' "$file" || problems="${problems}- no implementa App\\Interfaces\\Main\\AssistantSkillTool (cliente) ni OwnerSkillTool (dueña)\n"
        grep -qE "=>[[:space:]]*(\\\\?App\\\\Ai\\\\Tools\\\\)?${class}::class" "$root/config/atendia.php" || problems="${problems}- falta su clave en config/atendia.php (assistant.skills) y su fila en AssistantSkillSeeder\n"
        if [ -n "$problems" ]; then
            {
                printf 'Regla de oro incumplida en %s: toda herramienta es un skill del asistente.\n' "$file"
                printf '%b' "$problems"
                printf 'Guía: .ai/guidelines/skills-del-asistente.md\n'
            } >&2
            exit 2
        fi
        ;;
    */app/Ai/Agents/*.php)
        for tool in "$root"/app/Ai/Tools/*.php; do
            name=$(basename "$tool" .php)
            if grep -qE "new[[:space:]]+${name}[[:space:]]*\(" "$file"; then
                {
                    printf 'Regla de oro incumplida en %s: el agente no instancia %s.\n' "$file" "$name"
                    printf 'Las herramientas las entregan App\\Services\\AssistantSkills u OwnerSkills (clave en el config + fila en el seeder).\n'
                    printf 'Guía: .ai/guidelines/skills-del-asistente.md\n'
                } >&2
                exit 2
            fi
        done
        ;;
esac

exit 0
