# Fechas — siempre Flatpickr

- Elegir una fecha o un rango = `<x-inputsform.datepicker>` (`mode="range"` para rango).
- Cero inputs nativos de fecha u hora en Blade, cero daterangepicker/moment/pikaday, y
  cero flatpickr por CDN (entra por npm al build de Vite).
- El valor viaja ISO en un hidden con el `wire:model` (`Y-m-d`, o `Y-m-d..Y-m-d`); el
  visible es `d/m/Y` y vive tras un wrapper `wire:ignore` (un morph de Livewire lo pisaría).
- Un rango cerrado con una sola fecha se completa como rango del mismo día.
- El tema del calendario vive en `app.css`, con selectores más específicos que los del
  paquete (su CSS llega después en el bundle).
- Siguen válidos los campos de fecha TIPEADA de `attribute-fields` (texto `d/m/Y`).

Candados: `GoldenRulesDatepickerTest` + `check-datepicker-golden-rules.sh`.
