---
name: client
description: Auditoría y corrección del panel del cliente (/dashboard) de AtendIa. La invoca bin/nightwatch.sh en cada corrida nocturna; también se puede usar a mano. Revisa contradicciones, reuso de componentes de diseño, correos por el canal Email, y que cada pantalla tenga su componente, su opción de menú y su nombre en inglés — respetando todas las reglas de oro.
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

## 2. Qué se revisa (los 11 puntos)

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
   `Plan`, textos que no se desdicen entre pantallas (título vs. mensaje,
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
    (deuda). **Una promesa sin candado se reporta en el log como hallazgo**, no
    se arregla sola — el cupo lo decide la dueña.

## 3. Reglas de oro — TODAS, sin excepción

Todo cambio respeta las guías de `.ai/guidelines/` (están en `CLAUDE.md`):
comentarios en inglés y cortos, formularios con `BaseForm`, filas declaradas,
cero avisos nativos, Flatpickr para fechas, queries en el modelo, tenancy,
property hooks, planes de una fuente, moderación, reportes, contrato IA,
migraciones seguras (**jamás** `migrate:fresh/refresh/reset` ni `db:wipe`
sobre `atendia`; columna nueva = rediseñar la create).

- Hacer **solo** lo que piden estos 11 puntos. Una mejora que no está acá se
  anota en el log como "mejora para decidir", no se implementa.
- Comandos de Laravel dentro del contenedor:
  `docker exec -w /var/www/html atendia-app <comando>`.
- Tests: Pest en inglés, con `--filter`. **NO correr la suite completa**: la
  corre `nightwatch.sh` como puerta del commit (regla: una completa por commit).
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

- `ESTADO: COMPLETO` solo si los 11 puntos quedaron revisados en todo el panel
  y no queda nada por arreglar. Si no, `PENDIENTE` (el script lanza otra corrida).
- La respuesta final de la sesión incluye además "Checklist de salida",
  "Verificación visual" y "Mejoras para decidir" (lo exige el hook Stop).
