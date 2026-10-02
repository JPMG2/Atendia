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
