# Un Blade jamás arma una query

- Ni el template ni el bloque PHP de un SFC construyen una consulta. Le piden el dato al
  MODELO por su nombre de dominio: `options()`, `serviceNames()`, `phoneFlags()`,
  `dialCode()`, `visibleTo()`…
- Prohibido en un `.blade.php`: `::query(`, `DB::`, los estáticos de query
  (`Modelo::where/find/all/first/firstWhere/pluck/orderBy/latest/oldest`) y `->orderBy(`.
- Puede quedar en el componente el armado de UI de UNA pantalla: filtrar una Collection ya
  cargada, `firstWhere` sobre opciones, `groupBy` para pintar. Filtrar en memoria es
  presentación, no query.
- El método nuevo del modelo lleva nombre de dominio y PHPDoc con el shape, y su contrato
  es consistente con sus hermanos (`$states` vacío = sin filtro).

Candados: `GoldenRulesBladeQueriesTest` + `check-blade-query-golden-rules.sh`.
