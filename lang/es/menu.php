<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Menú del dashboard (opciones temporales del skeleton)
|--------------------------------------------------------------------------
|
| Las claves son las que guarda la columna `label_key` de la tabla menus
| (ej. label_key 'menu.home' → 'Inicio'). Variantes regionales sobrescriben
| solo lo que cambie en lang/es_AR/menu.php, etc. (fallback a es).
*/

return [
    'section' => 'Menú',
    'aria_nav' => 'Navegación principal',

    // Upsell card del footer del sidebar (skeleton temporal)
    'plan_name' => 'Plan Inicial',
    'plan_trial' => 'Quedan 9 días de prueba.',
    'plan_cta' => 'Mejorar plan',

    'home' => 'Inicio',
    'conversations' => 'Conversaciones',
    'agenda' => 'Agenda',
    'products' => 'Productos',
    'products_catalog' => 'Catálogo',
    'products_categories' => 'Categorías',
    'products_categories_active' => 'Activas',
    'metrics' => 'Métricas',
    'settings' => 'Ajustes',
    'help' => 'Ayuda',

    // Panel admin (configuración)
    'admin_home' => 'Inicio',
    'admin_users' => 'Usuarios',
    'admin_settings' => 'Configuración',
];
