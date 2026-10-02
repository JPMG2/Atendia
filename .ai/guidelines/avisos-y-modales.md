# Avisos — cero avisos nativos

- Prohibido `alert()`, `confirm()` y `prompt()` (ni con `window.`) en Blade y en JS.
- Todo aviso sale de `<livewire:dialog />`, montado una sola vez en el layout, y se abre con
  la función global `dialog.*` (`resources/js/dialog.js`), que devuelve una promesa:
  `dialog.confirm({title, message, accept, type})`, `dialog.notify(…)`, `dialog.retry(…)`.
- `type`: `info | success | warning | danger`. `danger` SOLO para lo que no se deshace.
- `accept` nombra la acción ("Eliminar la red"), no un "Aceptar" genérico. Escape, click
  afuera y Cancelar son lo mismo: no ejecutan nada.
- **Toast vs. diálogo**: lo que ya pasó y no pide respuesta es toast
  (`dispatchNotification()`); el diálogo es cuando hace falta una respuesta para seguir.
- Copy por `__()`; los rótulos por defecto en `lang/es/dialog.php`.

Candados: `GoldenRulesDialogTest` + `check-no-native-alerts.sh`.
