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
    'sidebar_toggle' => 'Compactar o expandir el menú',
    'open_menu' => 'Abrir menú',
    'notifications' => 'Notificaciones',

    // Upsell card del footer del sidebar (skeleton temporal)
    'profile_progress' => 'Tu perfil, al :percent%',
    'profile_missing' => [
        'personal_data' => 'Faltan tus datos personales',
        'tax_details' => 'Faltan tus datos fiscales',
        'schedule' => 'Faltan tus horarios de atención',
        'social_media' => 'Faltan tus redes sociales',
    ],
    'profile_done' => 'Listo. Tu asistente ya responde por ti.',
    'profile_done_close' => 'Ocultar el aviso',
    'plan_name' => 'Plan :plan',
    'plan_trial' => 'Te quedan :days días de prueba.',
    'plan_cta' => 'Ver mi plan',

    'home' => 'Inicio',
    'plan' => 'Mi plan',
    'assistant' => 'Mi asistente',
    'assistant_knowledge' => 'Conocimiento',
    'assistant_settings' => 'Configuración',
    'customers' => 'Clientes',
    'agenda' => 'Agenda',
    'statistics' => 'Estadísticas',
    'referrals' => 'Gana con :brand',
    'conversations' => 'Conversaciones',
    'my_business' => 'Mi negocio',
    'team' => 'Equipo',
    'catalog' => 'Catálogo',
    'services' => 'Servicios',
    'products' => 'Productos',
    'whatsapp' => 'WhatsApp',
    'settings' => 'Ajustes',
    'plan_payments' => 'Plan y pagos',
    'my_payments' => 'Mis pagos',
    'admin_payments' => 'Pagos',
    'admin_ai' => 'Modelos de IA',
    'admin_ai_usage' => 'Consumo de IA',
    'admin_moderation' => 'Moderación',
    'admin_support' => 'Soporte',
    'admin_adoption' => 'Adopción',
    'help' => 'Ayuda',

    // Panel admin (configuración)
    'admin_home' => 'Inicio',
    'admin_businesses' => 'Negocios',
    'admin_all_businesses' => 'Todos',
    'admin_billing' => 'Cobros',
    'admin_trust' => 'Moderación',
    'admin_content' => 'Contenido',
    'admin_platform' => 'Plataforma',
    'admin_users' => 'Usuarios',
    'admin_catalogs' => 'Catálogos',
    'admin_testimonials' => 'Testimonios',
    'admin_company' => 'Compañía',
    'admin_integrations' => 'Integraciones',
    'admin_logs' => 'Logs del sistema',
    'admin_settings' => 'Ajustes',
    'admin_roles' => 'Roles y permisos',
    'admin_audit' => 'Auditoría',
];
