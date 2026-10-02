# Economía de tokens con laravel/ai — checklist de todo agente o skill

> Cada token lo paga el negocio. Toda tarea que toque `app/Ai` (agente, skill,
> instrucciones) se cierra recorriendo esta lista. Lo verificable por patrón está
> blindado (`GoldenRulesAgentEconomyTest` + `GoldenRulesAgentContractTest` + hook
> `check-ai-agent-golden-rules.sh`); lo demás es criterio y se dice al cerrar.

## Checklist (en orden de impacto)

1. **Lo fijo arriba, lo que cambia abajo.** OpenAI cachea solo el PREFIJO idéntico
   (≥1024 tokens) y lo cobra mucho más barato. Orden: `grounding` → rol y reglas →
   briefings por cliente → `clock` ÚLTIMO. Un dato que cambia en la primera línea
   anula el caché de todo lo que sigue. (`#[CacheInstructions]` de laravel/ai solo
   actúa con Anthropic: con OpenAI la palanca es el orden.) *Blindado.*
2. **Una pasada, no dos.** Un re-prompt duplica la factura: solo con un pre-filtro
   en PHP que lo justifique (ej. `looksLikeEnquiry`).
3. **Filtrar en PHP antes de llamar.** Lo que una regla decide (saludo, vacío,
   duplicado) no llega al modelo.
4. **Memoria acotada.** Todo Conversational tiene `MEMORY_LIMIT`. *Blindado.*
5. **Herramientas que contestan datos, no párrafos.** Líneas cortas y exactas;
   "no hay dato" dicho. Un RAG manda ~800 tokens; un skill, una línea.
6. **Skills del rubro diferidos con ToolSearch**, universales directos (`skills-del-asistente.md`).
7. **Salida estructurada** (`HasStructuredOutput`) para clasificar o extraer:
   nada de pedir JSON en texto y parsearlo.
8. **Modelo por tarea, declarado.** `#[Model]` explícito en todo agente *(blindado)*.
   Tareas mecánicas (traducir, mapear columnas, corregir nombres) → evaluar el
   barato de OpenAI (`gpt-6-luna`) **midiendo**, no por intuición.
9. **Medir antes y después**: `php artisan atendia:ai-costs`. Una optimización sin
   número es una opinión.

## Al cerrar una tarea que tocó `app/Ai`

Una línea por punto que aplique: qué se hizo o por qué no aplica. Si se ve una
palanca mayor (ej. modelo barato en un agente mecánico), se ofrece en UNA línea y
decide ella.
