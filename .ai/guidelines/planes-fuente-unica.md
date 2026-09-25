# Planes — UNA sola fuente (regla de oro)

> Orden de la dueña (2026-09-25): "es imposible que nos contradigamos en los
> planes". Nació de la landing prometiendo 5 números de WhatsApp mientras el
> sistema daba 4. Un tema por archivo; el enforcement de 3 capas en
> `reglas-de-oro-enforcement.md`.

Blindada: test guardián `tests/Feature/GoldenRulesPlanSourceTest.php` + hook
`.claude/hooks/check-plan-source-golden-rules.sh` (mismos patrones, tocar de a dos).

## La fuente

- **Tabla `plans`** (modelo `SubscriptionPlan`, seeder `PlanSeeder` con
  `updateOrCreate`): precio, cupos, estadísticas, consultas a la IA, días de
  prueba (`trial_days`, solo el plan de la prueba) y el "Más elegido".
- **Se lee SOLO por `App\Classes\Main\Plan`**: `Plan::named()`, `ladder()`,
  `trial()`, `->yearlyPrice`, `->annualMonthlyPrice`, `->annualSavings`,
  `->features` (las líneas de TODA ficha: landing y "Mi plan").
- El catálogo va cacheado; guardar una fila lo invalida solo.

## Qué va en lang y qué no

- En lang van las PALABRAS con `:cap`, `:days`, `:plan`. Nunca la cifra.
- Lo que no es una cifra del plan (ej. "Agenda o catálogo") vive en
  `landing.pricing.{code}.extras`.

## Checklist

- [ ] ¿Precio, cupo o días de prueba en un Blade/lang? → sale de `Plan`.
- [ ] ¿Ficha nueva de planes? → usa `$plan->features`, no arma viñetas propias.
- [ ] `./vendor/bin/pest --filter=GoldenRulesPlanSource` en verde.
