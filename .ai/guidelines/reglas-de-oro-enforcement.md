# Reglas de oro — receta de enforcement (convención del proyecto)

> Para que una "regla de oro" se cumpla **a rajatabla** no alcanza con escribirla:
> una guía es contexto pasivo y se puede pasar por alto. La garantía real la da
> una verificación **determinística** que corre la herramienta, no el modelo.

Toda regla de oro de este proyecto se implementa con **3 capas**. Las dos primeras
hacen que casi siempre salga bien; la tercera lo hace **imposible de incumplir**.

## Capa A — Skill con checklist de salida
- Las reglas viven en un **skill** con `description` (trigger) inequívoca que
  active al entrar al dominio (p. ej. "usar SIEMPRE al crear un formulario o un
  componente Livewire").
- El skill **termina con un checklist explícito** que debo verificar **antes de
  dar la tarea por terminada**. Convierte la regla en un paso de salida, no en un
  buen deseo.

## Capa B — Garantía determinística (SIEMPRE, si es regla de oro)
Elegí la herramienta según el dominio:
- **PHP (modelos, clases)** → **arch test de Pest** (`arch()`), hecho para esto.
- **Migraciones / markup Blade** → **test guardián**: un test Pest que recorre los
  archivos del dominio y **falla** si encuentra un patrón prohibido.
- Ambos corren en la suite / CI → protegen también ediciones de humanos u otras
  herramientas. Es la red permanente.

## Capa C — Hook `PostToolUse` (corrección instantánea)
- Un hook en `.claude/settings.json` que matchea `Write|Edit` sobre los archivos
  del dominio, valida el archivo recién escrito y, si viola algo, **devuelve el
  error en el momento** (exit 2) para corregir antes de que corran los tests.

## Cómo se clasifica cada regla
Cuando se suma un set de reglas de oro:
1. Separar cada regla en **verificable por patrón** (va a capa B y C) vs **de
   criterio/UX** (queda solo en el checklist del skill, capa A).
2. Definir un **allowlist de excepciones** explícito y comentado para no generar
   falsos positivos (primitivos, casos legítimos, deuda pre-existente).
3. **Ratchet:** si hay incumplimientos previos que no se arreglan ahora, se
   congelan en el allowlist con su razón — **nunca se agrega nada nuevo a esa
   lista**, se arregla.

## Implementaciones vivas (ejemplos de esta receta)
- **Formularios / markup** → checklist en skill `atendiadesign` · test guardián
  `tests/Feature/GoldenRulesMarkupTest.php` · hook
  `.claude/hooks/check-blade-golden-rules.sh`.
- **No perseguir un flake** → memoria `atendia-feedback-modo-trabajo` (capa A) ·
  hook `.claude/hooks/block-browser-suite-reruns.sh` (capa C), que bloquea la
  TERCERA corrida de la suite de browser entera. Escrito ya había estado y se
  incumplió igual varios días seguidos: por eso hay hook.
- **Una corrida de la suite entera por commit** → memoria
  `atendia-feedback-modo-trabajo` (capa A) · hook
  `.claude/hooks/block-full-suite-reruns.sh` (capa C), que bloquea la SEGUNDA
  corrida completa si no hubo un commit en el medio. Mientras se trabaja va
  `--filter`; la completa es la puerta del commit. Escrito estaba, y se
  incumplió el mismo día: 4 corridas (~350s) donde hacía falta una.
- **Formularios / layout (aprovechar el ancho)** → `.ai/guidelines/formularios.md` §5 +
  checklist del skill · test guardián `tests/Feature/GoldenRulesFormLayoutTest.php` ·
  hook `.claude/hooks/check-catalog-form-layout.sh`.
- **Comentarios / PHPDoc en inglés y cortos** → `.ai/guidelines/comentarios.md` ·
  test guardián `tests/Feature/GoldenRulesCommentsTest.php` · hook
  `.claude/hooks/check-comment-golden-rules.sh`. Las capas B y C comparten el
  MISMO scanner (`tests/Support/CommentScanner.php`), así que no pueden divergir
  —a diferencia de los allowlists espejados de la regla de markup, que hay que
  tocar de a dos—. El ratchet llegó a cero y se borró: hoy no hay allowlist.
  Ojo con el ALCANCE del scanner: un `.blade.php` puede ser un SFC de Livewire y
  llevar su clase entera en un bloque `<?php`; mirar solo los `{{-- --}}` deja sin
  vigilar la mitad del archivo (pasó, y los docblocks quedaron en español).
- **Queries en el modelo (un Blade jamás arma una query)** →
  `.ai/guidelines/queries-en-el-modelo.md` · test guardián
  `tests/Feature/GoldenRulesBladeQueriesTest.php` · hook
  `.claude/hooks/check-blade-query-golden-rules.sh`. Allowlists espejados
  (tocar de a dos); los verbos compartidos con Collection no se prohíben.
- **Migraciones / modelos** → *(pendiente: skill propio + `arch()` para modelos +
  test guardián para migraciones cuando se sumen las reglas).*

Ver también: [[documentacion-y-memoria]] (un tema por archivo, legibilidad).
