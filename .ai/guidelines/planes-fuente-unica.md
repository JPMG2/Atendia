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

## Una fuente no alcanza: la cifra tiene que ser VERDAD (2026-09-29)

> Una sola fuente evita que dos pantallas se contradigan **entre sí**. No evita
> que el sistema entero contradiga **lo que vende**: "2 números de WhatsApp"
> viajó meses de la landing a "Mi plan", coherente en las tres pantallas y falso
> en el producto. Lo cazó la dueña, no la suite.

Blindada: test guardián `tests/Feature/GoldenRulesPlanPromiseTest.php`. Toda
cifra que una ficha vende cae en **exactamente un cajón**, y el guardián se pone
rojo si aparece una cuarta sin clasificar:

- **`LOCKS`** — dónde se hace cumplir (archivo real, que además tiene que seguir
  leyendo el dial de `Plan`: si se renombra o se vacía, el test cae).
- **`SOFT`** — blanda **a propósito**, con la decisión escrita (las
  conversaciones del mes no cortan: ese WhatsApp es la caja del cliente).
- **`PENDING`** — se vende sin nada detrás. Es deuda: una entrada **sale** de la
  lista cuando se construye su candado, y sumar una es una edición deliberada
  que alguien tiene que escribir.

Dos cosas que NUNCA cuentan como candado (`PRINTS_ONLY`): el skill que le
**recita** el plan a la dueña (`OwnerPlanUsage`) y las fichas/medidores de
`components/plan/`. Mostrar la cifra no es hacerla cumplir.

El punto 11 de la skill `client` lo audita cada noche: una promesa sin candado se
**reporta** en el log, no se arregla sola — el cupo lo decide la dueña.

## Checklist

- [ ] ¿Precio, cupo o días de prueba en un Blade/lang? → sale de `Plan`.
- [ ] ¿Ficha nueva de planes? → usa `$plan->features`, no arma viñetas propias.
- [ ] ¿Cifra nueva vendida? → su cajón en `GoldenRulesPlanPromiseTest` (`LOCKS`,
      `SOFT` con razón, o `PENDING` como deuda declarada).
- [ ] `./vendor/bin/pest --filter="GoldenRulesPlanSource|GoldenRulesPlanPromise"` en verde.
