# Tenancy — un cliente jamás ve a otro

- Todo modelo cuya tabla tenga `business_id` usa el trait `App\Traits\BelongsToBusiness`
  (global scope + sello al crear + relación `business()`). No es disciplina: es el trait.
- Modelo tenant nuevo → columna `business_id` en su migración `create_*` + el trait, y
  sumar la tabla a la migración `enable_tenant_row_level_security` (RLS de Postgres, activo).
- Sin sesión (jobs, consola, seeders) el contexto se adopta explícito:
  `Tenant::for($businessId, fn () => ...)`. Jamás `Auth::user()` dentro de `handle()`;
  el job viaja con el `business_id` en el payload.
- `withoutGlobalScope` está PROHIBIDO en `app/` y en las vistas. El escape legítimo es
  `Tenant::for()`, que deja rastro y restaura.
- Canales de broadcast: el nombre lleva el tenant (`private-business.{id}.…`) y la
  autorización compara contra `$user->business_id`.
- Archivos bajo `businesses/{business_id}/…`, y al servirlos se re-chequea el dueño.
- Query cruda o join: cada tabla joineada filtra su `business_id`.
- Excepciones con razón: `users.business_id` es membresía, no dato tenant; `social_links`
  se aísla por su dueño (`$business->socialLinks()`), nunca por `find($id)` suelto.

Candados: `GoldenRulesTenancyTest` (trait exigido por introspección del esquema),
`BusinessIsolationTest` (dos negocios sobre los modelos tenant) y `check-tenancy-golden-rules.sh`.
