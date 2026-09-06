# Patrones de composición (curados de Tailwind UI / Tailwind Plus)

> Los planos que hacen que una pantalla salga linda a la primera. Cada patrón se
> escribe con NUESTROS tokens (jade, `app.css`) — un bloque de Tailwind Plus se
> **traduce**, jamás se pega tal cual. El dueño tiene cuenta de Tailwind Plus:
> para mirar un bloque puntual se usa la extensión *Claude in Chrome* sobre su
> sesión (nunca pedir credenciales por chat).

## Regla madre: traducir, no pegar

- **Paleta**: `gray-800`, `indigo-500`, `white/10` → SIEMPRE tokens de la casa
  (`var(--brand)`, `--surface-sunken`, `--border-subtle`, helpers `text-strong`,
  `bg-brand-soft`…). Un hex o color crudo de la paleta Tailwind es un bug.
- **Dark mode**: los bloques de Tailwind traen variantes `dark:` por clase; acá el
  claro/oscuro sale SOLO de los tokens — casi nunca hace falta `dark:`.
- **Versión**: los ejemplos nuevos de tailwindcss.com son **v4**; este proyecto es
  **v3**. Tabla de traducción:

| v4 (ejemplos oficiales) | v3 (lo nuestro) |
| --- | --- |
| `outline-hidden` | `outline-none` |
| `not-checked:before:hidden` | invertir con `checked:` / `peer-checked:` |
| `has-checked:` / `group-has-checked:` | `has-[:checked]:` / `group-has-[:checked]:` (v3.4) |
| `inset-ring`, `ring` nuevos | `ring-1 ring-inset` |
| `size-4` | `size-4` (existe desde v3.4) |

## 1. Lista apilada (stacked list) — filas que forman UN bloque

El patrón del fieldset de radios de Tailwind UI. Las filas comparten bordes
hairline, solo las puntas del grupo redondean, y la fila destacada "salta" entera.

- Colapso de bordes: `-space-y-px` en el contenedor (o `margin-top:-1px` por fila).
- Redondeo en las puntas: `first:rounded-t-md last:rounded-b-md`.
- La fila resaltada sube con `relative`/`z-index` para que su borde gane a los vecinos.
- Resalte = **wash + borde + tinte del texto**, los tres juntos: fondo
  `--brand-soft`, borde `--brand-border`, label `--brand-soft-text`.
- **Implementación viva de la casa**: `.setup-list` / `.setup-step` en `app.css`
  (guía del Inicio del cliente). Reusar esas clases antes de reinventar.

## 2. Estado por selector, sin JavaScript

El input pinta la fila entera: el contenedor lleva `group`, el estado se lee con
`has-[:checked]:` (el propio contenedor) o `group-has-[:checked]:` (un hijo).
Sirve para radio-cards, checkboxes que iluminan su tarjeta, filas seleccionables.
En Livewire, si el estado ya viaja al server, la vía de la casa es `@class([...])`
con el booleano — el selector CSS es para estado puramente visual/instantáneo.

## 3. Filas de lista con hover

- Separadores con `divide-y` (un solo borde entre filas), no borde por fila.
- Hover: wash `--surface-sunken` en la fila completa, transición `--dur-fast`.
  Nunca solo el texto: la FILA entera responde (regla de oro de densidad).
- Meta a la derecha (hora, monto) en `font-mono` + `--text-subtle`.

## 4. Empty state que enseña

Tile de icono en `--brand-soft` + título display + una línea que dice QUÉ va a
aparecer ahí + (si aplica) la acción que lo llena. Skeleton de barras para
"preview del estado poblado". Implementación viva: `.preview-card` en `app.css`.

## 5. Cabecera de sección / página

Título `font-display` a la izquierda, acción primaria a la derecha, sub en
`--text-muted` debajo del título. Implementación viva: `.page-head`. En cards:
mismo patrón en chico (`.setup-head`, `.recent-head`).

## 6. Formularios (recordatorio)

Los form layouts de Tailwind UI (label arriba, hint chico, grupos en fieldset)
acá ya están resueltos por los componentes de la casa: `<x-ui.*>` /
`<x-inputsform.*>` + `<x-catalog.form-row>`. Ver `.ai/guidelines/formularios.md`
— NO armar forms con utilidades sueltas.

## 7. Checklist con progreso (setup guide)

Barra de progreso fina (`999px`, track `--surface-sunken`, fill `--brand`),
conteo "N de M" en `font-mono` `--text-subtle`, ítems como lista apilada (§1),
el paso completado con tick lleno `--brand` y label apagado, el paso héroe con
el resalte completo. Implementación viva: `.setup-*` en `app.css`.

## Checklist al traducir un bloque de Tailwind Plus

- [ ] Cero colores de la paleta Tailwind: todo por token/helper de la casa.
- [ ] Sintaxis v4 traducida a v3 (tabla de arriba).
- [ ] ¿Ya existe el patrón como clase (`.setup-*`, `.preview-card`, `.page-head`)
      o componente (`<x-ui.*>`)? Reusar antes de crear.
- [ ] Densidad y respiración de la casa (skill `atendiadesign`): compacto, 20–24px
      de padding interno, hover en lo accionable.
- [ ] Claro/oscuro y responsive verificados (tokens, no `dark:` por clase).
