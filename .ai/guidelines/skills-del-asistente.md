# Skills del asistente — todo lo que la IA pueda manejar, es un skill (regla de oro)

> Todo lo que programemos —un módulo nuevo, un cambio en uno existente, o algo
> que ya está y descubrimos que el asistente podría resolver— se expone como
> **skill** del asistente (`laravel/ai`) si la IA lo puede manejar. Un skill es
> lo que el asistente sabe HACER con datos reales del negocio: una herramienta,
> no un archivo de instrucciones.

Esta regla está **blindada**: el test guardián
`tests/Feature/GoldenRulesAssistantSkillsTest.php` y el hook
`check-assistant-skill-golden-rules.sh`. Nació el 2026-09-24, con los skills
de catálogo, horarios y contacto. Un tema por archivo: el costo y el medidor
viven en la memoria `atendia-medidor-consumo-ia`, la tenancy en `tenancy.md`.

## Por qué

- **Dato exacto en vez de texto:** un skill contesta con una línea de la base
  ("abierto hasta las 12", el precio exacto). La búsqueda en documentos manda
  fragmentos de ~800 tokens y la IA tiene que encontrar el dato adentro.
- **Lo que un documento no sabe:** el día y la hora de ahora, el stock actual,
  un turno libre. Solo una consulta en vivo lo sabe.
- **N clientes, N rubros sin pagar por todos:** los skills del rubro viajan
  diferidos con ToolSearch y se cargan solo cuando la consulta los necesita.
- **Un módulo que el asistente no puede usar es medio módulo:** el dueño lo
  carga en el panel y el cliente de WhatsApp no se entera.

## La pregunta obligatoria

Al terminar cualquier módulo o cambio: **¿un cliente del negocio podría
preguntar o pedir esto por WhatsApp?** (consultar, reservar, confirmar,
cancelar, saber si hay…). Si la respuesta es sí, lleva skill. Si es no (un
ajuste interno del panel, la facturación de AtendIa), se dice en una línea por
qué no.

## Dos públicos, un solo catálogo (2026-09-25)

- **Cliente** (WhatsApp, `AsistenteAtendia`): implementa `AssistantSkillTool`,
  lo entrega `App\Services\AssistantSkills`, fila con `audience = customer`.
- **Dueña** ("Pregúntale a AtendIa", `AskAtendia`): implementa
  `OwnerSkillTool` (solo lectura), lo entrega `App\Services\OwnerSkills`,
  fila con `audience = owner`. Fechas explícitas AAAA-MM-DD (trait
  `ReadsDateRange`): el agente traduce "hoy"/"ayer" con el reloj de sus
  instrucciones. Cómo funciona el panel = skill `panel_guide`, que lee los
  MISMOS textos de las pantallas (`atendia.owner_assistant.guide`).
- Jamás se cruzan: un dato del negocio no se le lee a un cliente. Lo blinda
  el guardián (audiencia de la fila = interfaz de la herramienta).

## Cómo se suma un skill

1. **La herramienta** en `app/Ai/Tools/`, implementando
   `App\Interfaces\Main\AssistantSkillTool`: `forAssistant()` la arma desde el
   contexto del asistente (negocio, charla, cliente) y devuelve `null` si le
   falta algo. El negocio se fija al construirla; **jamás** sale de un argumento
   que escribe el modelo.
2. **La clave** en `config/atendia.php` → `assistant.skills` (clave → clase).
3. **La fila** en `AssistantSkillSeeder`: `is_universal` si sirve a todo
   negocio; si es del rubro, `false` y se asigna a sus actividades (tabla
   `activity_assistant_skill`, es dato). Los del rubro viajan con ToolSearch.
4. **El agente no instancia herramientas**: `AsistenteAtendia::tools()` las
   pide a `App\Services\AssistantSkills`. Así una clave apagada (`is_active`)
   desaparece de todos los asistentes sin tocar código.
5. **La respuesta es corta y exacta**: datos, no párrafos. Si no hay dato, lo
   dice ("El negocio no cargó sus horarios"), para que la IA no improvise.
6. **Las instrucciones** del asistente nombran qué skill usar para qué.
7. **Test Pest** del skill (respuesta, aislamiento por negocio) y medir con
   `atendia:ai-costs` el antes/después cuando aplique.

## Checklist de salida

- [ ] ¿El cliente podría pedir esto por WhatsApp? Si sí, hay skill; si no, una línea del porqué.
- [ ] Implementa `AssistantSkillTool`; el negocio viene del contexto, nunca del modelo.
- [ ] Clave en `atendia.assistant.skills` y fila en `AssistantSkillSeeder` (universal o del rubro).
- [ ] El agente no hace `new` de ninguna herramienta.
- [ ] Respuesta corta, exacta y con el "no hay dato" dicho.
- [ ] Test Pest en inglés + `--filter=GoldenRulesAssistantSkills` en verde.
