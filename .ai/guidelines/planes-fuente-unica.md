# Planes — una sola fuente, y la cifra tiene que ser verdad

- La fuente es la tabla `plans` (`SubscriptionPlan`, seeder `PlanSeeder` con `updateOrCreate`):
  precio, cupos, estadísticas, consultas a la IA, días de prueba y el "Más elegido".
- Se lee SOLO por `App\Classes\Main\Plan`: `named()`, `ladder()`, `trial()`, `->yearlyPrice`,
  `->annualSavings`, `->features` (las líneas de TODA ficha). Guardar una fila invalida el caché.
- En `lang` van las PALABRAS con `:cap`, `:days`, `:plan`. **Nunca la cifra.** Lo que no es
  cifra del plan va en `landing.pricing.{code}.extras`.
- Una sola fuente evita que dos pantallas se contradigan entre sí; no evita que el producto
  contradiga lo que vende. Toda cifra vendida cae en un cajón de `GoldenRulesPlanPromiseTest`:
  `LOCKS` (dónde se hace cumplir), `SOFT` (blanda a propósito, con la razón escrita) o
  `PENDING` (deuda: se vende sin candado). Mostrar la cifra no es hacerla cumplir.

Candados: `GoldenRulesPlanSourceTest`, `GoldenRulesPlanPromiseTest` +
`check-plan-source-golden-rules.sh`.
