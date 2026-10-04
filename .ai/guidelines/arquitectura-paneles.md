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
- El menú se piensa como ÁRBOL: una opción nueva entra como hija de su padre, no como un
  ítem suelto más. El padre se decide antes de sembrar la fila.
- La pantalla vive en `resources/views/components/<opción del menú>/<nombre en inglés>.blade.php`.
  En el admin la opción va bajo el panel: `components/admin/<opción>/<nombre>.blade.php`.
- Lo que se configura, se configura DESDE el admin, con el patrón de `Catálogos` (maestro en
  BD + seeder `updateOrCreate`). Un Enum de `app/Enums` se queda en código si el código lo
  ramifica; si es una lista que ella podría renombrar, reordenar o ampliar, va a BD.
- Los datos de la plataforma (nombre de marca, dirección, contacto, redes, logo, tagline,
  copyright) salen de la fila de `companies` vía `Company::current()`. Ni `config()`, ni una
  constante, ni texto escrito en una vista: la marca va a cambiar de nombre y tiene que ser
  una fila editada.
