# Skills del asistente — lo que la IA pueda manejar, es un skill

Al terminar cualquier módulo: **¿un cliente del negocio podría pedir esto por WhatsApp?**
Si sí, lleva skill. Si no, se dice en una línea por qué no.

- La herramienta va en `app/Ai/Tools/` implementando `App\Interfaces\Main\AssistantSkillTool`:
  `forAssistant()` la arma desde el contexto (negocio, charla, cliente) y devuelve `null` si
  le falta algo. **El negocio se fija al construirla; jamás sale de un argumento del modelo.**
- Clave en `config('atendia.assistant.skills')` + fila en `AssistantSkillSeeder`
  (`is_universal`, o del rubro y asignado a sus actividades).
- **El agente no instancia herramientas**: las pide a `AssistantSkills` / `OwnerSkills`, así
  apagar una clave la saca de todos los asistentes sin tocar código.
- Dos públicos que no se cruzan: cliente (`AssistantSkillTool`, `audience = customer`) y
  dueña (`OwnerSkillTool`, solo lectura, `audience = owner`, fechas explícitas AAAA-MM-DD).
- La respuesta es corta y exacta: datos, no párrafos, y el "no hay dato" dicho.
- Las instrucciones del asistente nombran qué skill usar para qué.

Candados: `GoldenRulesAssistantSkillsTest` + `check-assistant-skill-golden-rules.sh`.
