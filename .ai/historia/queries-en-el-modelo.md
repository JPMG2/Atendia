# Queries en el modelo — un Blade jamás arma una query (regla de oro)

> Un `.blade.php` (el template O el bloque PHP de un SFC de Livewire) nunca
> construye una consulta a la base. Pide el dato al **modelo** por su nombre
> de dominio: `options()`, `suggestionsName()`, `serviceNames()`,
> `phoneFlags()`, `dialCode()`, `idFromCode()`, `visibleTo()`…

Esta regla está **blindada**: el test guardián
`tests/Feature/GoldenRulesBladeQueriesTest.php` y el hook
`check-blade-query-golden-rules.sh` fallan el build si un Blade arma una query.
Nació el 2026-09-05: el usuario cazó 3 queries armadas en los steps del wizard
y en la auditoría aparecieron 4 más — la clase de agujero se cierra acá.

## Por qué

- **DRY de decisiones, no de teclas.** Un query inline repite el *contrato*
  (qué filtra `$states`, en qué orden sale la lista) y los contratos copiados
  a mano divergen: el bug de `phoneFlags()` (un `$states` muerto pisado por un
  hardcode) nació exactamente así.
- **El segundo llamador ya tiene fecha.** La pantalla de edición de
  configuración del cliente va a pedir los mismos datos; si el query vive en
  el modelo, la decisión de copiar no existe.
- **Testabilidad**: un método del modelo se prueba sembrando y llamando; el
  mismo query dentro del componente exige montar el Livewire completo.

## La línea (qué va dónde)

- **Al modelo**: lo que expresa vocabulario del dominio — algo que un segundo
  llamador pediría con las mismas palabras ("los nombres sugeridos de este
  rubro", "el prefijo telefónico de este país").
- **Puede quedar en el componente**: armado de UI de UNA pantalla (filtrar una
  Collection ya cargada, `firstWhere` sobre opciones, `groupBy` para pintar).
  Por eso los verbos compartidos con Collection (`->where`, `->pluck`) NO se
  prohíben por patrón: filtrar en memoria es presentación.
- Si un modelo engorda demasiado, el paso siguiente son query objects — con el
  3º caso, no antes (ver memoria `atendia-cuando-abstraer`).

## Patrones prohibidos en un `.blade.php`

`::query(` · `DB::` · estáticos de query (`Modelo::where/find/all/first/
firstWhere/pluck/orderBy/latest/oldest`) · `->orderBy(` (verbo exclusivo del
builder: en un Blade delata una query encadenada a una relación).

## Ratchet

Deuda congelada con razón (NUNCA agregar entradas, se arregla el archivo):
`components/⚡ws-demo.blade.php` (demo de desarrollo, muere en go-live).
El allowlist vive espejado en el test guardián y el hook: se tocan de a dos.

## Checklist de salida

- [ ] Ningún `::query()` / `DB::` / estático de query / `->orderBy(` en Blade.
- [ ] El método nuevo del modelo tiene nombre de dominio y PHPDoc con el shape.
- [ ] Contrato consistente con sus hermanos (`$states` vacío = sin filtro).
- [ ] `./vendor/bin/pest --filter=GoldenRulesBladeQueries` en verde.
