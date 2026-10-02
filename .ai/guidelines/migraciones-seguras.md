# Migraciones — nunca borrar datos de `atendia`

- `migrate:fresh`, `migrate:refresh`, `migrate:reset` y `db:wipe` están PROHIBIDOS sobre
  `atendia`. La única base donde se testea es `atendia_testing`.
- Aplicar lo nuevo: `php artisan migrate`, o quirúrgico
  `php artisan migrate --path=database/migrations/<archivo>.php`.
- **Columna nueva en tabla existente (antes del go-live): NO se crea una migración
  `add_*`/`drop_*`/`rename_*`.** Se suma a la migración `create_*` y se sincroniza
  `atendia` con un `Schema::table()` quirúrgico en tinker. Si hubo una `add_*` temporal
  aplicada, se borra el archivo Y su fila en `migrations`.
- Migración nueva → aplicarla a `atendia` (si no, la feature no existe en el sitio real)
  y sembrar con `db:seed --class=<Seeder>`.

Blindaje: `DB::prohibitDestructiveCommands()` gateado por la base ACTIVA (no por
`isProduction()`), el guard de `tests/TestCase.php`, `phpunit.xml` y el hook
`block-destructive-db.sh`. Regresión: `DestructiveCommandGuardTest`.
