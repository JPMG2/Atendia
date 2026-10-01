---
name: client
description: Auditoría y corrección del panel del cliente (/dashboard) de AtendIa. La invoca bin/nightwatch.sh en cada corrida nocturna; también se puede usar a mano. Revisa contradicciones, reuso de componentes de diseño, correos por el canal Email, que lo que un cliente pediría por WhatsApp sea un skill, y que cada pantalla tenga su componente, su opción de menú y su nombre en inglés — respetando todas las reglas de oro y todos los hooks de .claude/hooks.
user-invocable: true
---

# Client — auditoría del panel del cliente

> Alcance: todo lo que cuelga de `/dashboard` con `permission:access-client-app`
> (rutas en `routes/web.php`, vistas en `resources/views/components/*`,
> ítems de menú con `panel = client`). El panel admin queda FUERA salvo que un
> arreglo del cliente lo toque por reuso.

## 0. Antes de tocar nada — cargar las skills

**Antes que todo**, leer entera `.ai/guidelines/no-mediocre.md` (reglas de
trabajo: cero mediocridad) y aplicarla en toda la corrida.

Después, invocar con la herramienta Skill, en este orden, y leer TODOS los
archivos de referencia que cada una trae (`reference/`, `rules/`):

1. `tailwindcss-development`
2. `atendiadesign` — todo lo de `.claude/skills/atendiadesign/` (SKILL.md +
   `reference/`). Sin esta skill cargada el hook `require-design-skill.sh`
   bloquea cualquier edición de vistas/CSS.
3. `laravel-best-practices` — SKILL.md + todo `rules/`.
4. `livewire-development` — SKILL.md + todo `reference/`.
5. `ai-sdk-development` — SKILL.md (si se toca `app/Ai`, además el checklist
   de `.ai/guidelines/ia-economia-tokens.md`).

## 1. Memoria entre corridas — leer PRIMERO el log

- Leer `bin/nightwatch-summary.log` entero antes de auditar.
- **No repetir** lo que una corrida anterior ya hizo o ya descartó con razón.
  Si un arreglo previo volvió a romperse, no se re-arregla igual: se busca la
  CAUSA y se anota por qué volvió.
- Si el log dice que algo quedó pendiente, se empieza por eso.

## 2. Qué se revisa (los 12 puntos)

1. Skill `tailwindcss-development` cargada y aplicada.
2. Skill `atendiadesign` completa cargada y aplicada (tokens, dark/light,
   responsive, `app.css`).
3. Skill `laravel-best-practices` completa cargada y aplicada.
4. Skill `livewire-development` completa cargada y aplicada.
5. Skill `ai-sdk-development` completa cargada y aplicada.
6. **Todos los correos salen por la clase del canal**
   `App\Messaging\Channels\Email` (`(new Email($modelo, [$destino], Mailable::class))->send()`).
   Cero `Mail::` fuera de `app/Messaging` (`.ai/guidelines/correo-por-canal.md`).
7. **Cero contradicciones en todo el panel del cliente**: el mismo concepto
   con el mismo nombre en todas las pantallas, cifras de planes solo desde
   `App\Classes\Main\Plan` (en `lang` van las PALABRAS con `:cap` / `:days` /
   `:plan`, **nunca la cifra**, y toda ficha usa `$plan->features` en vez de
   armar sus propias viñetas — `.ai/guidelines/planes-fuente-unica.md`),
   textos que no se desdicen entre pantallas (título vs. mensaje,
   menú vs. encabezado, tuteo `es` / voseo `es_AR`), comportamientos iguales
   para acciones iguales (Cancelar `danger`, avisos por `dialog.*`, toasts).
8. **Se usan los componentes de diseño y no se repite markup**: `<x-ui.*>`,
   `<x-inputsform.*>`, `<x-catalog.*>`, `<x-icon>`. Markup de diseño copiado
   entre vistas se sube a un componente existente (o a uno nuevo con su test
   Pest si no existe).
9. **No se elimina ninguna clase creada.** Ni clases PHP, ni componentes, ni
   tests. Se puede refactorizar su interior; borrar, jamás. Si una parece
   muerta, se anota en el log y decide la dueña.
10. **Cada pantalla del panel tiene**: su componente en
    `resources/views/components/<área>/`, su opción en el menú (tabla `menus`,
    `panel = client`, sembrada en su seeder) y su nombre (archivo, carpeta,
    clase, ruta) **en inglés**. El texto visible sigue en español por `__()`.
11. **Lo que el plan promete, el sistema lo cumple.** Que una cifra salga de
    `Plan` (punto 7) no la hace verdad: tres pantallas pueden decir "2 números"
    de forma perfectamente coherente y falsa — pasó, y lo cazó la dueña, no la
    suite (2026-09-29). Cada cifra o promesa vendida (landing incluida, aunque
    esté fuera del panel) necesita **un candado en código** que la haga cumplir,
    declarado en `tests/Feature/GoldenRulesPlanPromiseTest.php`: `LOCKS` (dónde
    se hace cumplir), `SOFT` (blando a propósito, con su razón) o `PENDING`
    (deuda). **Mostrar la cifra no es hacerla cumplir**: el skill que se la
    recita a la dueña (`OwnerPlanUsage`) y los medidores de `components/plan/`
    son `PRINTS_ONLY` y no cuentan como candado. **Una promesa sin candado se
    reporta en el log como hallazgo**, no se arregla sola — el cupo lo decide
    la dueña.
12. **Lo que un cliente pediría por WhatsApp, es un skill.** Toda pantalla del
    panel que cargue datos del negocio (servicios, productos, horarios, agenda,
    contacto, clientes…) cierra con la pregunta obligatoria de
    `.ai/guidelines/skills-del-asistente.md`: *¿un cliente del negocio podría
    preguntar o pedir esto por WhatsApp?* Si sí, ese dato tiene su herramienta
    `AssistantSkillTool` (`audience = customer`), con su clave en
    `atendia.assistant.skills` y su fila en `AssistantSkillSeeder` (universal o
    del rubro); si no, se dice en una línea por qué no. Lo de la dueña es
    `OwnerSkillTool` (solo lectura) y **jamás se cruzan**: un dato del negocio
    no se le lee a un cliente. Se arregla en la corrida lo verificable (la clave
    o la fila que falta, un agente haciendo `new` de una herramienta, una
    respuesta sin el "no hay dato" dicho); **una pantalla sin su skill se
    reporta en el log** — un skill nuevo es un módulo y lo decide la dueña.

## 3. Reglas de oro — TODAS, sin excepción, y sus candados

Todo cambio respeta las guías de `.ai/guidelines/` (están en `CLAUDE.md`):
comentarios en inglés y cortos, formularios con `BaseForm`, filas declaradas,
cero avisos nativos, Flatpickr para fechas, queries en el modelo, tenancy,
property hooks, planes de una fuente, skills del asistente, moderación,
reportes, contrato IA, migraciones seguras (**jamás**
`migrate:fresh/refresh/reset` ni `db:wipe` sobre `atendia`; columna nueva =
rediseñar la create).

### 3.1 Los hooks de `.claude/hooks` se cumplen, no se esquivan

Cada regla de oro tiene 3 capas (`.ai/guidelines/reglas-de-oro-enforcement.md`):
la guía (A), el test guardián `GoldenRules*` (B) y **el hook** (C). Los de
`.claude/hooks`, registrados en `.claude/settings.json`, corren solos en cada
corrida y la corrida cierra con **todos** en verde:

- **Los 18 guardianes `Write|Edit`** — markup (`check-blade-golden-rules.sh`),
  queries en Blade (`check-blade-query-golden-rules.sh`), layout de filas
  (`check-catalog-form-layout.sh`), avisos nativos
  (`check-no-native-alerts.sh`), Flatpickr (`check-datepicker-golden-rules.sh`),
  comentarios (`check-comment-golden-rules.sh`), controles que contestan
  (`check-live-control-golden-rules.sh`), consolidación de migraciones
  (`check-migration-consolidation.sh`), orden de campos
  (`check-field-order.sh`), validación en el Form
  (`check-form-validation-golden-rules.sh`), tenancy
  (`check-tenancy-golden-rules.sh`), property hooks
  (`check-php-getter-golden-rules.sh`), canal de correo
  (`check-mail-channel-golden-rules.sh`), skills del asistente
  (`check-assistant-skill-golden-rules.sh`), fuente de los planes
  (`check-plan-source-golden-rules.sh`), contrato y economía de los agentes
  (`check-ai-agent-golden-rules.sh`), moderación de subidas
  (`check-upload-moderation-golden-rules.sh`) y reportes
  (`check-report-golden-rules.sh`): si uno devuelve error, **se arregla el
  archivo**. Nunca se relaja el patrón, nunca se agrega una entrada a un
  allowlist (el ratchet solo baja) y nunca se escribe el archivo por otra vía
  para que el hook no llegue a correr.
- **`require-design-skill.sh`**: sin `atendiadesign` cargada no se editan
  vistas ni CSS, y **escribirlos por Bash** (`sed -i`, `perl -i`, `python`,
  `tee`, redirección) está prohibido — por esa vía los 17 de arriba no corren.
- **`block-destructive-db.sh`**: cero `migrate:fresh/refresh/reset` y `db:wipe`
  que no apunten explícitamente a `atendia_testing`.
- **`block-full-suite-reruns.sh`** y **`block-browser-suite-reruns.sh`**: una
  sola corrida de la suite completa por commit (la hace `nightwatch.sh`, no la
  corrida) y una sola de `tests/Browser`. Lo que falle se re-corre con
  `--filter`, que no cuenta; si pasa aislado es flake y **no se persigue**: se
  anota.
- **`block-blind-overwrite.sh`**: `Write` es para archivos NUEVOS. Sobre uno que
  ya existe con contenido, bloquea: se lee primero y se cambia con `Edit`. Nació
  el 2026-09-30, cuando un `Write` pisó entero `lang/es/notifications.php` (el
  copy de los toasts de toda la app) por suponer que el nombre estaba libre.
- **`block-swallowed-test-output.sh`**: una corrida de pest **no** se canaliza
  por `tail`/`head`/`grep` — el pipe retiene la salida hasta el final y la
  corrida parece colgada. Va a un archivo y se lee el archivo. Nació el
  2026-09-30 tras matar un pest que ya había terminado en 33 segundos.
- **`require-browser-suite-before-commit.sh`**: `nightwatch.sh` lo evalúa antes
  de commitear. Si se tocaron vistas/CSS/JS y `tests/Browser` no corrió
  **después** del último cambio, el trabajo de la corrida queda sin commit.
- **`remind-form-checklist.sh`** no bloquea: inyecta el checklist de criterio.
  Se recorre igual antes de dar una pantalla por terminada.
- **`fix-storage-perms.sh`** y **`reload-fpm-after-composer.sh`** se ocupan
  solos de los permisos y de recargar php-fpm; si alguno falla, se anota.
- **`inject-work-rules.sh`** (UserPromptSubmit): reinyecta las 7 reglas de
  `no-mediocre.md` con cada mensaje. Son las mismas de §0 y valen toda la
  corrida, no solo el turno en que aparecen.
- **`enforce-turn-exit.sh`** (Stop): el turno no cierra sin los guardianes en
  verde y, si hubo vistas, sin los tres bloques finales de §4 en la respuesta.
- Un hook que da un **falso positivo** se anota en el log como hallazgo. No se
  desactiva, no se saca de `settings.json`, no se le baja el patrón.

Esta lista está **blindada**: `tests/Feature/GoldenRulesClientSkillSyncTest.php`
se pone rojo si un hook registrado en `.claude/settings.json` no está nombrado
acá — un candado que la corrida no conoce no lo cumple nadie. Hook nuevo = su
línea en esta sección.

### 3.2 Ratchet — una regla que se rompió gana su candado en la misma corrida

Si la auditoría encuentra una regla de oro incumplida y esa regla **no tenía**
control automático, la corrida no cierra solo arreglando el archivo: se le suma
la capa B (test guardián en `tests/Feature/GoldenRules*Test.php`) y/o la capa C
(hook en `.claude/hooks` + su línea en `.claude/settings.json`). Una regla que
se rompió estando solo escrita es una regla sin cerradura.

- Separar lo **verificable por patrón** (va al guardián y al hook) de lo de
  **criterio** (queda en el checklist de la guía y en el log).
- Patrones y allowlists viven **espejados** en el test y en el hook: se tocan de
  a dos o divergen. Al allowlist de un ratchet **no se suma nada**: se arregla.
- ¿Se escribió una guía nueva en `.ai/guidelines/`? Correr
  `docker exec -w /var/www/html atendia-app php artisan boost:update` para que
  entre a `CLAUDE.md`; si no, `GoldenRulesGuidelinesSyncTest` se pone rojo y la
  regla nunca llega al agente.

### 3.3 Operación

- Hacer **solo** lo que piden estos 12 puntos. Una mejora que no está acá se
  anota en el log como "mejora para decidir", no se implementa.
- Comandos de Laravel dentro del contenedor:
  `docker exec -w /var/www/html atendia-app <comando>`.
- Tests: Pest en inglés, con `--filter`. **NO correr la suite completa**: la
  corre `nightwatch.sh` como puerta del commit (regla: una completa por commit).
  Los guardianes de las reglas tocadas sí se corren con `--filter="GoldenRules…"`.
- Si se tocaron vistas/CSS/JS: `view:clear` + `npm run build` (en el
  contenedor) y **una** corrida de `tests/Browser`
  (`docker exec -w /var/www/html -u www-data atendia-app ./vendor/bin/pest --compact tests/Browser`).
  Lo que falle se re-corre con `--filter`; si pasa aislado es flake, se anota.
- PHP tocado: `vendor/bin/pint --dirty --format agent`.
- **No commitear ni pushear**: lo hace `nightwatch.sh` tras verificar la suite.

## 4. Cierre — escribir en el log (obligatorio)

Agregar al FINAL de `bin/nightwatch-summary.log` (nunca reescribir lo anterior)
una sección con este formato exacto:

```
## Corrida N — AAAA-MM-DD HH:MM
### Hecho
- <archivo o pantalla>: <qué se cambió y por qué> (punto X)
### Revisado sin cambios
- <qué se miró y estaba bien>
### Pendiente / descartado
- <qué quedó y por qué; qué NO hay que volver a tocar>
### Mejoras para decidir
- <una línea cada una>
COMMIT: <subject en inglés, imperativo, ≤ 72 caracteres>
ESTADO: COMPLETO | PENDIENTE
```

- `ESTADO: COMPLETO` solo si los 12 puntos quedaron revisados en todo el panel,
  los hooks de §3.1 quedaron en verde y no queda nada por arreglar. Si no,
  `PENDIENTE` (el script lanza otra corrida).
- La respuesta final de la sesión incluye además "Checklist de salida",
  "Verificación visual" y "Mejoras para decidir" (lo exige el hook Stop).
