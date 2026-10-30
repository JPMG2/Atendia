# Las queries viven en el modelo

- Ni un Blade (template o bloque PHP de un SFC), ni un Form (`app/Livewire/Forms`), ni un
  form request, ni un controlador construyen una consulta. Le piden el dato al MODELO por su
  nombre de dominio: `options()`, `forEditing()`, `editable()`, `visibleTo()`…
- Prohibido ahí: `::query(`, `DB::`, `->orderBy(`, una cadena de relación
  (`->services()->where(`) y todo estático de un modelo que el modelo no declara (`with`,
  `where`, `find`, `count`, `firstOrCreate`…). Guardar en `tryAction()` (`Modelo::create`) no
  es una consulta.
- Puede quedar en el componente el armado de UI de UNA pantalla: filtrar una Collection ya
  cargada, `firstWhere` sobre opciones, `groupBy` para pintar. Filtrar en memoria es
  presentación, no query.
- El método nuevo del modelo lleva nombre de dominio y PHPDoc con el shape, y su contrato
  es consistente con sus hermanos (`$states` vacío = sin filtro).

Candados: `GoldenRulesBladeQueriesTest` + `check-blade-query-golden-rules.sh`.
