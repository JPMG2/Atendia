# Tenancy — un cliente JAMÁS ve a otro (regla de oro)

> El aislamiento por negocio NO es disciplina ("acordate de filtrar"): es el
> trait `BelongsToBusiness` + el singleton `Tenant`. Un tema por archivo: acá
> vive el *cómo* del aislamiento; los paneles/roles en `arquitectura-paneles.md`
> y el enforcement de 3 capas en `reglas-de-oro-enforcement.md`.

Esta regla está **blindada**: `tests/Feature/GoldenRulesTenancyTest.php`
(trait obligatorio por INTROSPECCIÓN del esquema — sin lista a mano — y cero
`withoutGlobalScope` en `app/`+vistas), `tests/Feature/BusinessIsolationTest.php`
(dataset con dos negocios que PRUEBA el aislamiento en los 6 modelos tenant)
y el hook `check-tenancy-golden-rules.sh`.

## Las piezas

- **`App\Traits\BelongsToBusiness`**: global scope `business_id = tenant actual`
  + sello de `business_id` al crear (solo si venía null) + la relación
  `business()`. Va en TODO modelo cuya tabla tenga `business_id` — el guardián
  lo exige solo, el día que el modelo nace (conversaciones incluidas).
- **`App\Services\Tenant`** (singleton): el negocio actual sale del usuario
  logueado; donde no hay sesión (jobs, consola, seeders) se adopta explícito
  con `Tenant::for($businessId, fn () => ...)` — restaura al salir, explote o no.
- **Excepciones con razón**: `users.business_id` es MEMBRESÍA, no dato tenant
  (scopearlo rompería auth y admin) — allowlist del guardián. `social_links`
  es polimórfica sin `business_id`: su aislamiento es que un link solo se
  alcanza A TRAVÉS de su dueño (`$business->socialLinks()`), nunca por
  `SocialLink::find($id)` suelto.

## Reglas al construir lo nuevo

1. **Modelo tenant nuevo** → columna `business_id` en su migración create +
   el trait. Nada más: el resto lo dan el scope y el sello.
2. **Jobs y colas**: el scope por auth es INERTE en un worker. El job viaja
   con el modelo o el `business_id` en el payload y adopta contexto con
   `Tenant::for()` — jamás `Auth::user()` dentro de `handle()`.
3. **Canales de broadcast (Reverb)**: el nombre SIEMPRE lleva el tenant
   (`private-business.{id}.…`) y la autorización compara contra
   `$user->business_id`. Nunca un canal keyed solo por id de conversación.
4. **Archivos**: bajo `businesses/{business_id}/…`; servir re-chequeando
   dueño (una URL firmada autentica la URL, no autoriza al que mira).
5. **`withoutGlobalScope` está PROHIBIDO** en `app/` y vistas (guardián +
   hook). El escape legítimo es `Tenant::for()`, que deja rastro y restaura.
6. **RLS de Postgres — ACTIVO desde el 2026-09-09** (migración
   `enable_tenant_row_level_security`): Postgres mismo cerca cada query —
   SQL crudo incluido — por `app.current_tenant`, que empuja `Tenant`
   (middleware `SetTenantDatabaseContext` en cada request web; `Tenant::for()`
   en jobs). `FORCE` porque la app conecta como DUEÑO de las tablas. Sin
   tenant seteado la policy abre (admin/consola/seeders), espejo del scope.
   Tabla tenant nueva → sumarla a la migración RLS (el guardián avisa si
   falta). Complementa al scope, nunca lo reemplaza.

## Checklist de salida

- [ ] ¿Tabla nueva con `business_id`? → trait puesto (el guardián avisa igual).
- [ ] ¿Job nuevo que toca datos tenant? → `Tenant::for()` + id en el payload.
- [ ] ¿Query cruda / join? → cada tabla joineada filtra su `business_id`.
- [ ] `./vendor/bin/pest --filter=GoldenRulesTenancy` y `--filter=BusinessIsolation` en verde.
