# AtendIa — convenciones del proyecto

> ⚠️ ESTE archivo (y cualquier `.md`/`.blade.php` dentro de `.ai/guidelines/`) es tuyo.
> Laravel Boost lo INCLUYE en `CLAUDE.md` al correr `boost:install`/`boost:update`,
> pero NUNCA lo sobreescribe. Pon aquí todos tus prompts/instrucciones personalizados.
> Nunca edites `CLAUDE.md` a mano: Boost lo regenera y se perdería.

## Entorno (Docker)

- El código vive en el host en `/var/www/atendia`, montado en vivo dentro del contenedor `atendia-app` en `/var/www/html`.
- PHP, Composer y Artisan corren **dentro del contenedor**, no en el host. Para cualquier comando de Laravel usar:
  `docker exec -w /var/www/html atendia-app php artisan <comando>`
- Tras correr `composer`/`npm` como root en el contenedor, devolver permisos:
  `chown -R 1000:1000 /var/www/atendia` y `chown -R 82:82 storage bootstrap/cache`

## Stack

- Laravel 13 sobre PHP 8.5. Autenticación con Breeze (stack Blade). **Livewire 4** para componentes interactivos.
- BD Postgres y Redis compartidos vía la infra de EasyPanel (ver `.env`).

## Detrás de Traefik

- `bootstrap/app.php` usa `trustProxies(at: '*')`. No quitarlo: sin eso los assets se generan en http detrás del proxy https y se rompe el CSS.

## Testing — Pest OBLIGATORIO (override de la regla de Boost)

> ⚠️ Esto **anula** la regla de Boost que dice "usar PHPUnit y convertir Pest a PHPUnit".
> En este proyecto los tests se escriben **siempre en Pest v4**. Nada de clases PHPUnit nuevas.

- Framework: **Pest v4** (`pestphp/pest`) + plugin **`pestphp/pest-plugin-livewire`** para testear componentes Livewire (`livewire(Componente::class)->...`).
- Sintaxis funcional: `test('...', function () { ... })` / `it(...)` con `expect()`. El `TestCase` se enlaza en `tests/Pest.php`.
- **Todo el testing en INGLÉS:** descripciones de `test()`/`it()`, comentarios dentro de los tests, nombres de archivos, helpers y datasets van en inglés. (El código de la app y el copy de la UI siguen en español; esta regla aplica solo a la capa de tests.)
- Crear tests con `php artisan make:test --pest {Nombre}` (NO `--phpunit`).
- Correr: `docker exec -w /var/www/html atendia-app ./vendor/bin/pest --compact` (o con un filtro/archivo). Cada cambio debe quedar cubierto y verde antes de cerrar.

### Base de datos de testing (Postgres real, producción blindada)

- Todo corre sobre **Postgres**, también los tests. Hay una base **dedicada** `atendia_testing` (owner `atendia_user`), separada de producción `atendia`.
- `phpunit.xml` fuerza `DB_CONNECTION=pgsql` + `DB_DATABASE=atendia_testing`; host/usuario/clave se heredan del `.env` (no se duplican secretos).
- **Blindaje:** `tests/TestCase.php` aborta cualquier test si el entorno no es `testing` o la base no es exactamente `atendia_testing`. **Jamás** apuntar los tests a `atendia`.
- La base `atendia_testing` se creó con el superusuario `laravel_user` del contenedor `ai_project_postgres-shared`:
  `CREATE DATABASE atendia_testing OWNER atendia_user;`
