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

];
