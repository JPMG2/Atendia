# Paneles — admin y cliente

- Dos paneles: `admin` (la dueña) y `client` (el negocio). Roles spatie `admin` / `client`,
  permisos de área `access-admin-panel` y `access-client-app`.
- Super-admin: `Gate::before` en `AppServiceProvider` → el rol `admin` pasa cualquier gate.
- El registro público asigna `client`. El rol `admin` solo por `AdminUserSeeder`, keyed a
  `ADMIN_EMAIL`; nunca por la web.
- **La cerradura va en middleware de ruta (`permission:…`) y/o policy, NUNCA en ocultar el
  menú.** Ocultar un link es UX.
- Área nueva: ruta en `routes/admin.php` (prefijo y permiso ya puestos) o bajo `/dashboard`;
  permiso nuevo en `RolesAndPermissionsSeeder`; componentes en `App\Livewire\Admin\*` o
  `App\Livewire\App\*`; y **tests de acceso sí o sí** (cliente↛admin = 403).
- Menú data-driven: tabla `menus` con `panel` y `permission`; `Menu::tree($panel)` filtra
  recursivo. Iconos en `config/icons.php`.
