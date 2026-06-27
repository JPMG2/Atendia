# Migraciones seguras — NUNCA borrar datos de `atendia`

> Incidente 2026-06-27: la base de trabajo `atendia` quedó sin datos. Regla dura
> para que sea imposible repetirlo. La base de producción/trabajo es **`atendia`**;
> la única base donde se testea es **`atendia_testing`**.

## Prohibido sobre `atendia`
- `php artisan migrate:fresh` · `migrate:refresh` · `migrate:reset` · `db:wipe`
  → dropean tablas y **borran todos los datos**. JAMÁS sobre `atendia`.
- Está blindado por el hook `PreToolUse` `.claude/hooks/block-destructive-db.sh`
  (bloquea esos comandos salvo que apunten explícitamente a `atendia_testing`).

## Cómo aplicar migraciones a `atendia`
- **Solo las pendientes:** `php artisan migrate` (no toca tablas ya migradas, no borra data).
- **Quirúrgico (preferido al aplicar UNA nueva):**
  `php artisan migrate --path=database/migrations/<archivo>.php`
  → corre **únicamente esa** migración. Ver convención del usuario.
- Recordá el entorno Docker: `docker exec -w /var/www/html atendia-app php artisan migrate ...`

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
