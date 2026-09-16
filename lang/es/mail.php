<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Correos — copy de los mensajes que salen del sistema
|--------------------------------------------------------------------------
|
| Base NEUTRA (tuteo). Está escrito sin verbos que cambien con el voseo, así
| que `es_AR` no necesita override; si se agrega uno, va parcial.
|
*/

return [

    // El chrome que visten todos los correos (components/email/layout).
    'layout' => [
        'rights' => '© :year AtendIa. Todos los derechos reservados.',
    ],

    'new_company' => [
        'subject' => ':name ya está en marcha',
        'preheader' => 'Razón social, identificación fiscal y domicilio ya encabezan tus facturas y el pie de tu sitio.',
        'eyebrow' => 'Registro confirmado',
        'title' => 'Tu compañía ya está en marcha',
        'intro' => 'Los datos de :name quedaron guardados: desde ahora encabezan cada factura emitida y el pie del sitio.',
        'legal_name' => 'Razón social',
        'tax_id' => 'Identificación fiscal',
        'address' => 'Dirección',
        'location' => 'Ubicación',
        'next_title' => 'Próximos pasos',
        'next_intro' => 'Con la compañía registrada, esto es lo que sigue para dejar la plataforma a punto.',
        'next_catalogs_title' => 'Catálogos',
        'next_catalogs_body' => 'Los maestros que alimentan el sistema: rubros, actividades, servicios y regiones, listos para revisar.',
        'next_catalogs_cta' => 'Abrir los catálogos',
        'next_integrations_title' => 'Integraciones',
        'next_integrations_body' => 'La salud de todo lo que la plataforma consume, reunida en un solo tablero.',
        'next_integrations_cta' => 'Ver las integraciones',
        'closing' => 'Gracias por confiar en nosotros.',
        'team' => 'El equipo de AtendIa',
        'reason' => 'Recibiste este correo porque esta dirección es el contacto registrado de la compañía.',
    ],

    'business_welcome' => [
        'subject' => ':name ya tiene su asistente',
        'preheader' => 'Tu asistente ya sabe presentarse. Conecta tu WhatsApp y empieza a atender por ti.',
        'eyebrow' => 'Bienvenido a AtendIa',
        'title' => 'Tu asistente ya está en marcha',
        'intro' => ':name ya tiene quién lo atienda: tu asistente sabe presentarse y está listo para aprender tu oficio.',
        'next' => 'Un solo paso lo separa de atender de verdad: conecta el WhatsApp de tu negocio.',
        'cta' => 'Conectar mi WhatsApp',
        'banner' => 'Tu negocio, atendido por IA.',
        'gains_title' => 'Esto es lo que tu negocio acaba de ganar',
        'gains_intro' => 'Dos ventajas que empiezan a trabajar desde el primer día.',
        'gain_always_title' => 'Atención 24 horas',
        'gain_always_body' => 'Tu asistente no duerme ni se toma feriados: responde al instante a las 3 de la tarde o a las 3 de la mañana, siempre con la voz de tu negocio.',
        'gain_always_cta' => 'Activar mi asistente',
        'gain_inbox_title' => 'Tus mensajes, a un solo click',
        'gain_inbox_body' => 'Cada conversación vive en tu panel: se lee y se responde con un click, desde el negocio, tu casa o la playa.',
        'gain_inbox_cta' => 'Abrir mi panel',
        'closing' => 'Gracias por elegirnos para atender tu negocio.',
        'team' => 'El equipo de AtendIa',
        'reason' => 'Recibiste este correo porque creaste tu negocio en AtendIa con esta dirección.',
    ],

    'contact_updated' => [
        'subject' => ':name tiene un nuevo correo de contacto',
        'preheader' => 'Este es el nuevo punto de encuentro entre tu negocio y AtendIa.',
        'eyebrow' => 'Seguimos de la mano',
        'title' => 'Tu contacto quedó al día',
        'intro' => 'El correo de contacto de :name se actualizó, y esta es su nueva dirección.',
        'body' => 'Aquí van a llegar las novedades de tu asistente y los avisos importantes de tu cuenta.',
        'alert' => 'Si no hiciste este cambio, entra a tu panel y revisa tus datos de contacto.',
        'cta' => 'Abrir mi panel',
        'closing' => 'Gracias por mantener tu negocio al día.',
        'team' => 'El equipo de AtendIa',
        'reason' => 'Recibiste este correo porque esta dirección quedó como el contacto de :name en AtendIa.',
    ],

    'challenge' => [
        'subject' => ':code es tu código para entrar',
        'preheader' => 'Alguien intenta entrar a tu cuenta desde un dispositivo nuevo. Este código lo confirma.',
        'eyebrow' => 'Código de acceso',
        'title' => 'Tu código para entrar',
        'intro' => 'Hola :name, tu cuenta intenta iniciar sesión desde un dispositivo nuevo. Este es el código para confirmarlo.',
        'body' => 'El código vence en 10 minutos y sirve una sola vez.',
        'alert' => 'Si no intentaste entrar, no compartas este código con nadie y cambia tu contraseña.',
        'closing' => 'Cuidar tu cuenta también es atenderte bien.',
        'reason' => 'Recibiste este correo porque tu cuenta de AtendIa intentó iniciar sesión en un dispositivo nuevo.',
    ],

    'new_device' => [
        'subject' => 'Nuevo inicio de sesión en tu cuenta',
        'preheader' => 'Detectamos un acceso desde un dispositivo que no habías usado antes.',
        'eyebrow' => 'Aviso de seguridad',
        'title' => 'Nuevo inicio de sesión',
        'intro' => 'Hola :name, tu cuenta inició sesión desde un dispositivo que no habíamos visto antes.',
        'browser' => 'Navegador',
        'ip' => 'Dirección IP',
        'location' => 'Ubicación aproximada',
        'time' => 'Fecha y hora',
        'body_ok' => 'Si fuiste tú, no hay nada que hacer: vamos a recordar este dispositivo.',
        'body_alert' => 'Si no reconoces este acceso, cambia tu contraseña ahora mismo.',
        'not_me' => 'No fui yo — cerrar ese dispositivo',
        'cta' => 'Cambiar mi contraseña',
        'closing' => 'Cuidar tu cuenta también es atenderte bien.',
        'team' => 'El equipo de AtendIa',
        'reason' => 'Recibiste este correo porque tu cuenta de AtendIa inició sesión en un dispositivo nuevo.',
    ],

];
