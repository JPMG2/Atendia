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
        'closing' => 'Gracias por elegirnos para atender tu negocio.',
        'team' => 'El equipo de AtendIa',
        'reason' => 'Recibiste este correo porque creaste tu negocio en AtendIa con esta dirección.',
    ],

];
