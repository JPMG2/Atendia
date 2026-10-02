# Un control que se dibuja, hace algo

- Todo `<button>`, `<x-ui.button>` o `<x-ui.icon-button>` lleva algo que lo hace andar:
  `wire:click`, `wire:submit`, `href`, `type="submit"`, `x-on:click`/`@click`, o un
  `data-…` que un script del bundle bindea. `data-testid` NO cuenta.
- Un componente que reenvía `$attributes` toma su acción del llamador y pasa. Un `<button>`
  sin ningún atributo pasa: manda el formulario donde vive.
- **Si la capacidad todavía no existe, no se dibuja el control.** El copy que la vende puede
  quedar; el botón no. Un componente que recibe el rótulo sin el método no imprime botón.
- El allowlist `ControlScanner::ALLOWED` no crece: un botón mudo se arregla, no se anota.

Candados: `GoldenRulesLiveControlsTest` + `check-live-control-golden-rules.sh`, con el MISMO
scanner (`tests/Support/ControlScanner.php`).
