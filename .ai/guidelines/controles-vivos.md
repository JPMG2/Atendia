# Controles vivos — un botón que se dibuja, hace algo (regla de oro)

> Si el panel dibuja un control que invita a actuar, el control actúa. Un botón
> con la misma pinta que todos los demás que contesta el click con silencio es
> una mentira: el cliente concluye que el producto está roto, no que la función
> todavía no existe. Un tema por archivo: acá vive el *cómo*; el enforcement de
> 3 capas en `reglas-de-oro-enforcement.md`.

Esta regla está **blindada**: el test guardián
`tests/Feature/GoldenRulesLiveControlsTest.php` y el hook
`check-live-control-golden-rules.sh` fallan si una vista dibuja un control sin
nada detrás. Las dos capas comparten el MISMO scanner
(`tests/Support/ControlScanner.php`), así que no pueden divergir.

## Por qué nació (2026-10-01)

La auditoría nocturna cazó esta misma clase de defecto **cuatro veces a mano**,
y cada caso había vivido meses porque nada se lo decía al build:

- el chip "WhatsApp conectado" de la barra, texto fijo que le decía *conectado*
  a todo cliente (muerto desde el 2026-06-27, cazado el 28/09);
- el punto rojo de la campana, encendido para todos desde junio;
- el **buscador de la barra**, el control más prominente de todas las pantallas:
  solo CSS, sin `wire:model` ni listener (sigue así, lo decide la dueña);
- el banner de IA imprimiendo **"Optimizar con IA" en dos pantallas** (Productos
  y Mi negocio → Identidad) sin método detrás.

## La regla

- Un `<button>`, un `<x-ui.button>` o un `<x-ui.icon-button>` lleva **algo que
  lo hace andar**: `wire:click`, `wire:submit`, `href`, `type="submit"`,
  `x-on:click`/`@click`, o un `data-…` que un script del bundle bindea
  (así alcanza `hero.js` la demo de la landing).
- `data-testid` **no cuenta**: es para los tests, no es un handler.
- Un componente que reenvía el `$attributes` toma su acción del llamador y por
  eso pasa (`ui/button`, `ui/icon-button`).
- Un `<button>` **sin un solo atributo** pasa: manda el formulario donde vive,
  que es exactamente lo que tiene que hacer.
- Si la capacidad todavía no existe, **no se dibuja el botón**. El copy que la
  vende puede quedar como nota; el control, no. Un componente que recibe el
  rótulo pero no el método **no imprime botón** (es el contrato de
  `<x-ui.ai-banner>`: sin `call`, el banner solo cuenta).

## Qué NO alcanza esta regla

- **`<a href="#">`** — un ítem de menú sin `route_name` (hoy "Ayuda") cae en
  ese markup. Es la misma mentira por otro mecanismo y está **reportada en el
  log, pendiente de la dueña**: se suma al guardián el día que se decida el
  remedio, no antes.
- **Un campo sin `wire:model`** — hoy el buscador de la barra, que es solo CSS.
  Mismo caso: reportado desde el 2026-09-30 y pendiente de la dueña (o se
  construye la búsqueda, o se saca el control). El guardián mira CONTROLES DE
  ACCIÓN; sumar los campos el día que esa decisión esté tomada.
- Un botón que **reporta un estado** en vez de ofrecer uno (el "Copiado" que se
  intercambia por dos segundos con el botón que sí copia). Esos viven en el
  allowlist `ControlScanner::ALLOWED` **con su razón escrita**, y esa lista
  **no crece**: un botón mudo se arregla, no se anota.

## Checklist de salida

- [ ] ¿Botón nuevo? Lleva `wire:click` / `href` / `x-on:click` / hook `data-…`.
- [ ] ¿La capacidad no existe todavía? No se dibuja el control; el copy queda y
      el hallazgo se reporta.
- [ ] Nada nuevo en `ControlScanner::ALLOWED`.
- [ ] `./vendor/bin/pest --filter=GoldenRulesLiveControls` en verde.
