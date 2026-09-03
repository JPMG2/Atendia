# Migraciones seguras — NUNCA borrar datos de `atendia`

> Incidente 2026-06-27: la base de trabajo `atendia` quedó sin datos. Regla dura
> para que sea imposible repetirlo. La base de producción/trabajo es **`atendia`**;
> la única base donde se testea es **`atendia_testing`**.

## Prohibido sobre `atendia`
- `php artisan migrate:fresh` · `migrate:refresh` · `migrate:reset` · `db:wipe`
  → dropean tablas y **borran todos los datos**. JAMÁS sobre `atendia`.

## Blindaje en 4 capas (por qué es imposible perder datos)

> Auditado el 2026-07-25 tras analizar cómo OTRO proyecto perdió toda su data: la
> protección nativa de Laravel estaba atada a `isProduction()` y, como el dato real
> vive en una base con `APP_ENV=local`, quedaba **apagada**. Acá lo cerramos.

1. **`DB::prohibitDestructiveCommands()` — la capa clave (nivel comando).** En
   `AppServiceProvider::configureCommands()`. **NO** se gatea por `app()->isProduction()`
   (agujero: `.env` es `APP_ENV=local` y el dato vive en `atendia` → daría `false` →
   protección apagada). Se gatea por la **base activa**: bloquea *siempre* salvo cuando
   la base es exactamente `atendia_testing`.
   ```php
   $connection = config('database.default');
   $database   = config("database.connections.{$connection}.database");
   DB::prohibitDestructiveCommands($database !== 'atendia_testing');
   ```
   Cubre el CLI directo **y** `RefreshDatabase` (que corre `migrate:fresh` por dentro),
   en Unit o Feature, **extienda o no** el guard de `TestCase`.
2. **Hook `PreToolUse`** `.claude/hooks/block-destructive-db.sh` (registrado en
   `.claude/settings.json`): bloquea (exit 2) cualquier Bash con esos comandos salvo que
   apunte explícitamente a `atendia_testing`. Ojo: el hook **no ve** `RefreshDatabase`
   (corre en PHP, no shell) → por eso la capa 1 es imprescindible.
3. **Guard `Tests\TestCase::guardAgainstProductionDatabase()`**: aborta si el entorno no
   es `testing` o la base no es `atendia_testing`. Agujero conocido: en `tests/Pest.php`
   se bindea SOLO a `Feature`+`Browser`, **no a `Unit`** — hoy inocuo porque la capa 1
   ya cubre Unit.
4. **`phpunit.xml`** fuerza `DB_CONNECTION=pgsql` + `DB_DATABASE=atendia_testing`.

Blindado con test de regresión: `tests/Feature/DestructiveCommandGuardTest.php` prueba
(seguro, con base descartable) que los 4 comandos quedan `prohibited` cuando la base ≠
`atendia_testing`.

## Cómo aplicar migraciones a `atendia`
- **Solo las pendientes:** `php artisan migrate` (no toca tablas ya migradas, no borra data).
- **Quirúrgico (preferido al aplicar UNA nueva):**
  `php artisan migrate --path=database/migrations/<archivo>.php`
  → corre **únicamente esa** migración. Ver convención del usuario.
- Recordá el entorno Docker: `docker exec -w /var/www/html atendia-app php artisan migrate ...`

## Columna nueva en tabla existente — REGLA DE ORO (2026-09-02, blindada)

> Mientras no haya go-live, **NO se crean migraciones `add_*`/`drop_*`/`rename_*`**:
> la columna se suma **rediseñando la migración de CREACIÓN** de la tabla.
> Incumplida el 2026-09-03 (dos `add_*` sobre `businesses`) → ahora blindada:
> test guardián `tests/Feature/GoldenRulesMigrationsTest.php` + hook
> `.claude/hooks/check-migration-consolidation.sh` (las `add_*` anteriores a la
> fecha son historia y quedan).

1. Sumar la columna a la migración `create_*_table` existente.
2. Sincronizar `atendia` con un **ALTER quirúrgico** (`Schema::table(...)` en tinker
   — no destructivo; la create ya corrió ahí y no se re-ejecuta).
3. `atendia_testing` se rearma sola vía RefreshDatabase → correr los tests.
4. Si hubo una `add_*` temporal aplicada, borrar el archivo Y su fila en `migrations`.

## Al crear una migración nueva (flujo completo)
1. Crear la migración (y modelo/seeder).
2. **Aplicarla a `atendia` con `migrate --path`** — si no, la feature no existe en el
   sitio real (la tabla solo viviría en `atendia_testing` vía tests).
3. Si corresponde, sembrar datos: `php artisan db:seed --class=<Seeder>` (no usa fresh).
4. Devolver permisos tras correr como root en el contenedor.

## Testing
- Los tests reales usan **RefreshDatabase** sobre `atendia_testing` (forzado en
  `phpunit.xml` + guard en `tests/TestCase.php`). No se usan los comandos `migrate:*`
  destructivos a mano para testear.

Relacionado: la receta de enforcement de 3 capas — ver `.ai/guidelines/reglas-de-oro-enforcement.md`.
