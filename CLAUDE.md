<laravel-boost-guidelines>
=== .ai/arquitectura-paneles rules ===

# Arquitectura de paneles (admin / cliente)

> AtendIa tiene **2 paneles**: `admin` (configuración, el dueño) y `client` (el
> negocio cliente). Patrón "panels" (lo que formalizan Filament/Nova), nativo con
> Livewire + spatie. Esta guía evita improvisar al sumar áreas, features o roles.

## Identidad y acceso

- Roles spatie: `admin`, `client`. Permisos de ÁREA: `access-admin-panel`,
  `access-client-app` (los finos se suman por feature).
- **Super-admin**: `Gate::before` en `AppServiceProvider` → el rol `admin` pasa
  cualquier gate/policy (por eso ve también el panel cliente).
- El registro público asigna `client`. El rol `admin` SOLO por `AdminUserSeeder`
  (keyed a `ADMIN_EMAIL` en config), nunca por la web. Cambiar de admin = cambiar
  `ADMIN_EMAIL` y re-correr el seeder (degrada al anterior con syncRoles).

## Seguridad — NO negociable

- La cerradura va en **middleware de ruta** (`permission:...`) y/o **policies**,
  NUNCA en ocultar el menú. Ocultar un link es solo UX.
- Cada área nueva se cubre con **tests de acceso** (cliente↛admin = 403, etc.),
  como en `tests/Feature/PanelAccessTest.php`.

## Cómo sumar un área/feature

1. **Ruta**: si es del panel admin, va en `routes/admin.php` (ya tiene prefijo
   `/admin`, names `admin.*` y `permission:access-admin-panel` desde `bootstrap/app.php`).
   El panel cliente cuelga de `/dashboard` con `permission:access-client-app`.
   Para un permiso fino, agregá `->middleware('permission:loquesea')` a la ruta.
2. **Permiso nuevo**: definilo en `RolesAndPermissionsSeeder` y asignalo a los roles
   que correspondan (el admin pasa por super-admin igual). Resetear cache de permisos.
3. **Controllers/Livewire**: en namespaces por área (`App\Livewire\Admin\*`,
   `App\Livewire\App\*`) para que cada panel crezca aislado.
4. **Tests de acceso** sí o sí.

## Menú (data-driven, por panel + permiso)

- Tabla `menus`: columnas `panel` (admin|client) y `permission` (nullable).
- `Menu::tree($panel)` filtra por panel y por permiso (ítem visible si `permission`
  null o `auth()->user()->can()`; admin pasa por super-admin). Filtrado recursivo.
- `Navigation` (Livewire SFC) fija el panel en `mount()` con `request()->routeIs('admin.*')`.
- Sembrar ítems con su `panel` (cliente = default `client`). Iconos en `config/icons.php`.

## Switch de panel (admin)

- En el dropdown del topbar, gateado por `@can('access-admin-panel')`: alterna entre
  "Panel admin" y "Ver panel cliente". Badge "Admin" en el sidebar en `/admin`.

## Pendientes de diseño (cuando toque)

- **Impersonación**: "actuar como" un cliente puntual (ver sus datos). Feature aparte
  (package `lab404/laravel-impersonate` o propia), ligada a tenancy. Hoy solo está el
  acceso a la ESTRUCTURA del panel cliente (super-admin).
- **Tenancy**: aislamiento de datos por cliente (global scopes / `tenant_id` / policies).
  Capa aparte, compatible con esta arquitectura.

Memoria relacionada: `atendia-paneles-roles`. Receta de enforcement (tests/hooks):
`.ai/guidelines/reglas-de-oro-enforcement.md`.

=== .ai/atendia rules ===

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

## Git — mensajes de commit en INGLÉS

- **Todos los mensajes de commit van en inglés** (subject + body). Imperativo, conciso (ej. `Add UI form components`, `Fix icon size prop`).
- Identidad: `JPMG2 <jpmorenog22@gmail.com>`. Remote SSH: `git@github.com:JPMG2/Atendia.git` (rama `main`).
- `.env` y secretos NUNCA se commitean (ya cubierto por `.gitignore`).
- Crear tests con `php artisan make:test --pest {Nombre}` (NO `--phpunit`).
- Correr: `docker exec -w /var/www/html atendia-app ./vendor/bin/pest --compact` (o con un filtro/archivo). Cada cambio debe quedar cubierto y verde antes de cerrar.

### Base de datos de testing (Postgres real, producción blindada)

- Todo corre sobre **Postgres**, también los tests. Hay una base **dedicada** `atendia_testing` (owner `atendia_user`), separada de producción `atendia`.
- `phpunit.xml` fuerza `DB_CONNECTION=pgsql` + `DB_DATABASE=atendia_testing`; host/usuario/clave se heredan del `.env` (no se duplican secretos).
- **Blindaje:** `tests/TestCase.php` aborta cualquier test si el entorno no es `testing` o la base no es exactamente `atendia_testing`. **Jamás** apuntar los tests a `atendia`.
- La base `atendia_testing` se creó con el superusuario `laravel_user` del contenedor `ai_project_postgres-shared`:
  `CREATE DATABASE atendia_testing OWNER atendia_user;`

=== .ai/documentacion-y-memoria rules ===

# Documentación y memoria — mantenerlas legibles

> Objetivo: que las guías y memorias **siempre se puedan leer**, sin que crezcan
> hasta el punto de "es demasiado grande, no la leo". Esa frase es un error: un
> archivo del proyecto nunca se rechaza.

## Reglas para guías (`.ai/guidelines/`) y docs del proyecto

- **Un tema por archivo.** Preferir varios archivos chicos y enfocados (p. ej.
  `api.md`, `frontend.md`) antes que un único archivo gigante.
- **Tabla de contenidos / secciones claras** al inicio de cada guía larga, para
  poder leer solo la parte que aplica.
- **Enlazar, no inflar.** Si un tema ya está cubierto en otra guía, enlazarlo en
  vez de repetir el contenido.

## Reglas para la memoria automática

- **Una idea por archivo de memoria.** Si una memoria se vuelve grande, partirla
  en varias y enlazarlas con `[[nombre]]`.
- **El índice `MEMORY.md`: una línea por entrada.** Nunca poner contenido en el
  índice, solo el puntero con un gancho corto.
- **Actualizar, no duplicar.** Si un dato cambia, editar la memoria existente; si
  quedó obsoleta, borrarla.

## Cómo leer archivos grandes (nunca rechazarlos)

Si un archivo es grande, NO negarse a leerlo. Usar una de estas vías:

- **Leerlo por tramos** (lectura parcial con offset/limit), no de una sola vez.
- **Delegarlo a un subagente** que lo lea entero y devuelva solo el resumen
  relevante, así el archivo grande no ocupa el contexto principal.

=== .ai/migraciones-seguras rules ===

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

=== .ai/reglas-de-oro-enforcement rules ===

# Reglas de oro — receta de enforcement (convención del proyecto)

> Para que una "regla de oro" se cumpla **a rajatabla** no alcanza con escribirla:
> una guía es contexto pasivo y se puede pasar por alto. La garantía real la da
> una verificación **determinística** que corre la herramienta, no el modelo.

Toda regla de oro de este proyecto se implementa con **3 capas**. Las dos primeras
hacen que casi siempre salga bien; la tercera lo hace **imposible de incumplir**.

## Capa A — Skill con checklist de salida

- Las reglas viven en un **skill** con `description` (trigger) inequívoca que
  active al entrar al dominio (p. ej. "usar SIEMPRE al crear un formulario o un
  componente Livewire").
- El skill **termina con un checklist explícito** que debo verificar **antes de
  dar la tarea por terminada**. Convierte la regla en un paso de salida, no en un
  buen deseo.

## Capa B — Garantía determinística (SIEMPRE, si es regla de oro)

Elegí la herramienta según el dominio:
- **PHP (modelos, clases)** → **arch test de Pest** (`arch()`), hecho para esto.
- **Migraciones / markup Blade** → **test guardián**: un test Pest que recorre los
  archivos del dominio y **falla** si encuentra un patrón prohibido.
- Ambos corren en la suite / CI → protegen también ediciones de humanos u otras
  herramientas. Es la red permanente.

## Capa C — Hook `PostToolUse` (corrección instantánea)

- Un hook en `.claude/settings.json` que matchea `Write|Edit` sobre los archivos
  del dominio, valida el archivo recién escrito y, si viola algo, **devuelve el
  error en el momento** (exit 2) para corregir antes de que corran los tests.

## Cómo se clasifica cada regla

Cuando se suma un set de reglas de oro:
1. Separar cada regla en **verificable por patrón** (va a capa B y C) vs **de
   criterio/UX** (queda solo en el checklist del skill, capa A).
2. Definir un **allowlist de excepciones** explícito y comentado para no generar
   falsos positivos (primitivos, casos legítimos, deuda pre-existente).
3. **Ratchet:** si hay incumplimientos previos que no se arreglan ahora, se
   congelan en el allowlist con su razón — **nunca se agrega nada nuevo a esa
   lista**, se arregla.

## Implementaciones vivas (ejemplos de esta receta)

- **Formularios / markup** → checklist en skill `atendiadesign` · test guardián
  `tests/Feature/GoldenRulesMarkupTest.php` · hook
  `.claude/hooks/check-blade-golden-rules.sh`.
- **Migraciones / modelos** → *(pendiente: skill propio + `arch()` para modelos +
  test guardián para migraciones cuando se sumen las reglas).*

Ver también: [[documentacion-y-memoria]] (un tema por archivo, legibilidad).

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- laravel/ai (AI) - v0
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/breeze (BREEZE) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2
- alpinejs (ALPINEJS) - v3
- tailwindcss (TAILWINDCSS) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
