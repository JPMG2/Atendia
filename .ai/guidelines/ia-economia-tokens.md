# Economía de tokens (todo lo que toque `app/Ai`)

1. **Lo fijo arriba, lo que cambia abajo.** OpenAI cachea solo el prefijo idéntico: orden
   `grounding` → rol y reglas → briefings → `clock` ÚLTIMO. Un dato que cambia en la primera
   línea anula el caché de todo lo que sigue.
2. **Una pasada, no dos.** Un re-prompt duplica la factura; solo con un pre-filtro en PHP que
   lo justifique (`looksLikeEnquiry`).
3. **Filtrar en PHP antes de llamar**: saludo, vacío o duplicado no llegan al modelo.
4. **Memoria acotada**: todo Conversational declara `MEMORY_LIMIT`.
5. **Herramientas que contestan datos, no párrafos**, con el "no hay dato" dicho.
6. Skills del rubro diferidos con ToolSearch; universales directos.
7. **Salida estructurada** (`HasStructuredOutput`) para clasificar o extraer.
8. **El par explícito** (`#[Provider]` + `#[Model]`) en todo agente, y el trait
   `RunsAssignedModel`: el atributo es el default escrito en código, la fila de `ai_tasks`
   manda (modelo, proveedor y respaldo). Tarea mecánica → evaluar el modelo barato MIDIENDO.
9. **Medir antes y después**: `php artisan atendia:ai-costs`. Una optimización sin número es
   una opinión.

Al cerrar una tarea que tocó `app/Ai`: una línea por punto que aplique.
Candados: `GoldenRulesAgentEconomyTest` + `check-ai-agent-golden-rules.sh`.
