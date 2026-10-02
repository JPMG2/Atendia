# Fechas — SIEMPRE Flatpickr (regla de oro)

> Orden de la dueña (2026-09-20): en todo módulo donde haya que **elegir una
> fecha o un rango de fechas**, se usa **`<x-inputsform.datepicker>`** (Flatpickr
> vestido con los tokens de la casa). Ni inputs nativos de fecha, ni otra
> librería, ni CDN. Un tema por archivo: acá vive el *cómo* de las fechas; el
> enforcement de 3 capas en `reglas-de-oro-enforcement.md`.

Esta regla está **blindada**: el test guardián
`tests/Feature/GoldenRulesDatepickerTest.php` y el hook
`.claude/hooks/check-datepicker-golden-rules.sh` fallan el build si aparece un
input nativo de fecha u otra librería de calendario.

## Por qué

- `type="date"`/`datetime-local` **no se tematizan**: el calendario lo dibuja el
  navegador con su idioma y su estilo — en el panel se lee como un error del
  sistema. Además `type="time"` ya nos mordió (recortaba los minutos).
- Las alternativas viejas (daterangepicker) arrastran **jQuery + moment.js**,
  dos dependencias pesadas que este stack no tiene ni quiere.
- Flatpickr es dependencia cero, entra por npm al build de Vite, habla español
  y su calendario entero se pinta con los tokens (claro/oscuro solos).

## Cómo se usa

```blade
{{-- Un día --}}
<x-inputsform.datepicker name="birthday" :label="__('...')" wire:model="form.birthday" />

{{-- Un rango --}}
<x-inputsform.datepicker name="dates" mode="range" wire:model.live="dates" />
```

- El valor real viaja **ISO** en un hidden con el `wire:model`: `"Y-m-d"` para un
  día, `"Y-m-d..Y-m-d"` para un rango. El visible muestra `d/m/Y` y es de
  Flatpickr (va tras un wrapper `wire:ignore`: un morph de Livewire lo pisaría).
- Un rango cerrado con UNA fecha se completa como rango del mismo día — si no,
  flatpickr lo borra en el click afuera (visto en browser test, 2026-09-20).
- El tema del calendario vive en `app.css` (sección "Flatpickr, vestido de la
  casa"): selectores más específicos que los del paquete porque su CSS llega
  DESPUÉS en el bundle. Grilla 238px = 7 días de 34px exactos.

## Qué NO alcanza esta regla

- Los campos de fecha **como dato tipeado** dentro de `attribute-fields`
  (texto `d/m/Y` con placeholder) son anteriores y siguen válidos: ahí el dato
  se tipea, no se elige. Si un maestro pide calendario, se migra al componente.

## Checklist de salida

- [ ] Elegir fecha/rango = `<x-inputsform.datepicker>`; cero `type="date"`,
      `datetime-local`, `month`, `week`, `time` en Blade.
- [ ] Cero moment/daterangepicker/pikaday, y cero flatpickr por CDN.
- [ ] `./vendor/bin/pest --filter=GoldenRulesDatepicker` en verde.
