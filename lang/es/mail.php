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
        'rights' => '© :year Atendia. Todos los derechos reservados.',
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
        'team' => 'El equipo de Atendia',
        'reason' => 'Recibiste este correo porque esta dirección es el contacto registrado de la compañía.',
    ],

    'business_welcome' => [
        'subject' => ':name ya tiene su asistente',
        'preheader' => 'Tu asistente ya sabe presentarse. Conecta tu WhatsApp y empieza a atender por ti.',
        'eyebrow' => 'Bienvenido a Atendia',
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
        'team' => 'El equipo de Atendia',
        'reason' => 'Recibiste este correo porque creaste tu negocio en Atendia con esta dirección.',
    ],

    'referral_link' => [
        'subject' => 'Tu enlace para ganar con Atendia',
        'preheader' => 'Compártelo con otros negocios y gana descuentos en tu factura.',
        'eyebrow' => 'Gana con Atendia',
        'title' => 'Este enlace es tuyo: compártelo y gana',
        'intro' => 'Cada negocio que llegue a Atendia gracias a :name te deja un premio.',
        'body' => 'Reenvía este correo o comparte el enlace donde quieras: quien se registre con él estrena Atendia con :days días de prueba gratis, y cuando pague su primer mes tú ganas un :percent% de descuento en tu próxima factura. Los descuentos se acumulan.',
        'cta' => 'Ver mis referidos',
        'qr_alt' => 'Código QR de tu enlace de recomendación',
        'qr_hint' => 'Imprime este código y pégalo en tu mostrador: quien lo escanea llega con tu enlace.',
        'reason' => 'Recibiste este correo porque tu negocio tiene su enlace de recomendación en Atendia.',
    ],

    'contact_updated' => [
        'subject' => ':name tiene un nuevo correo de contacto',
        'preheader' => 'Este es el nuevo punto de encuentro entre tu negocio y Atendia.',
        'eyebrow' => 'Seguimos de la mano',
        'title' => 'Tu contacto quedó al día',
        'intro' => 'El correo de contacto de :name se actualizó, y esta es su nueva dirección.',
        'body' => 'Aquí van a llegar las novedades de tu asistente y los avisos importantes de tu cuenta.',
        'alert' => 'Si no hiciste este cambio, entra a tu panel y revisa tus datos de contacto.',
        'cta' => 'Abrir mi panel',
        'closing' => 'Gracias por mantener tu negocio al día.',
        'team' => 'El equipo de Atendia',
        'reason' => 'Recibiste este correo porque esta dirección quedó como el contacto de :name en Atendia.',
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
        'reason' => 'Recibiste este correo porque tu cuenta de Atendia intentó iniciar sesión en un dispositivo nuevo.',
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
        'team' => 'El equipo de Atendia',
        'reason' => 'Recibiste este correo porque tu cuenta de Atendia inició sesión en un dispositivo nuevo.',
    ],

    'account' => [
        'team' => 'El equipo de Atendia',

        'password_reset' => [
            'subject' => 'Restablece tu contraseña de Atendia',
            'preheader' => 'Un clic y eliges una contraseña nueva.',
            'eyebrow' => 'Tu contraseña',
            'title' => 'Elige una contraseña nueva',
            'intro' => 'Hola :name, pediste restablecer la contraseña de tu cuenta.',
            'body' => 'Toca el botón para elegir una nueva. El enlace vence en :minutes minutos.',
            'alert' => 'Si no lo pediste, ignora este correo: tu contraseña sigue igual.',
            'cta' => 'Restablecer mi contraseña',
            'reason' => 'Recibiste este correo porque alguien pidió restablecer la contraseña de tu cuenta de Atendia.',
        ],

        'two_factor_eyebrow' => 'Aviso de seguridad',
        'two_factor_alert' => 'Si no fuiste tú, cambia tu contraseña ahora mismo.',
        'two_factor_cta' => 'Cambiar mi contraseña',
        'two_factor_reason' => 'Recibiste este correo porque cambió la verificación en dos pasos de tu cuenta de Atendia.',
        'two_factor_on' => [
            'subject' => 'Activaste la verificación en dos pasos',
            'preheader' => 'Desde ahora, los códigos de acceso llegan a tu WhatsApp.',
            'title' => 'Tu cuenta quedó más protegida',
            'intro' => 'Hola :name, activaste la verificación en dos pasos por WhatsApp.',
            'body' => 'Cuando alguien entre desde un dispositivo nuevo, el código va a llegar a tu WhatsApp. Guarda tus códigos de respaldo por si pierdes el teléfono.',
        ],
        'two_factor_off' => [
            'subject' => 'Desactivaste la verificación en dos pasos',
            'preheader' => 'Los códigos de acceso vuelven a llegar por correo.',
            'title' => 'Apagaste la verificación en dos pasos',
            'intro' => 'Hola :name, la verificación en dos pasos de tu cuenta quedó desactivada.',
            'body' => 'Los códigos para entrar desde un dispositivo nuevo vuelven a llegar a este correo.',
        ],

        'verify' => [
            'subject' => 'Verifica tu correo de Atendia',
            'preheader' => 'Un clic y tu cuenta queda protegida.',
            'eyebrow' => 'Verificación',
            'title' => 'Verifica tu correo',
            'intro' => 'Hola :name, confirma que esta dirección es tuya:',
            'body' => 'Es la que usamos para tus avisos de seguridad. El enlace vence en :minutes minutos.',
            'cta' => 'Verificar mi correo',
            'closing' => 'Así nos aseguramos de que tu cuenta llegue solo a ti.',
            'reason' => 'Recibiste este correo porque pediste verificar tu dirección en Atendia.',
        ],

        'email_change' => [
            'subject' => 'Confirma tu nuevo correo de acceso',
            'preheader' => 'Un clic y este correo pasa a ser el de tu cuenta.',
            'eyebrow' => 'Confirmación',
            'title' => 'Confirma tu nuevo correo',
            'intro' => 'Hola :name, pediste usar esta dirección para entrar a Atendia.',
            'body' => 'Toca el botón para confirmarla. El enlace vence en :minutes minutos y, hasta que lo uses, sigues entrando con tu correo actual.',
            'alert' => 'Si no pediste este cambio, ignora este correo: nada va a cambiar.',
            'cta' => 'Confirmar mi nuevo correo',
            'closing' => 'Así nos aseguramos de que tu cuenta llegue solo a ti.',
            'reason' => 'Recibiste este correo porque alguien pidió usar esta dirección en una cuenta de Atendia.',
        ],

        'email_notice' => [
            'subject' => 'Pidieron cambiar el correo de tu cuenta',
            'preheader' => 'Si fuiste tú, no hay nada que hacer. Si no, frénalo con un clic.',
            'eyebrow' => 'Aviso de seguridad',
            'title' => 'Pidieron cambiar tu correo',
            'intro' => 'Hola :name, tu cuenta pidió pasar a usar esta nueva dirección para entrar:',
            'body' => 'Si fuiste tú, no hay nada que hacer: el cambio se completa cuando confirmes desde el nuevo correo.',
            'alert' => 'Si no fuiste tú, cancela el cambio ahora y cambia tu contraseña.',
            'not_me' => 'No fui yo — cancelar el cambio',
            'cta' => 'Cambiar mi contraseña',
            'closing' => 'Cuidar tu cuenta también es atenderte bien.',
            'reason' => 'Recibiste este correo porque es el correo de acceso actual de tu cuenta de Atendia.',
        ],

        'email_updated' => [
            'subject' => 'Tu correo de acceso quedó al día',
            'preheader' => 'Desde ahora entras a Atendia con esta dirección.',
            'eyebrow' => 'Seguimos de la mano',
            'title' => 'Tu correo quedó al día',
            'intro' => 'Hola :name, este es tu nuevo correo para entrar a Atendia:',
            'body' => 'Aquí van a llegar los avisos de seguridad y las novedades importantes de tu cuenta.',
            'alert' => 'Si no hiciste este cambio, entra a tu panel y revisa tus ajustes.',
            'cta' => 'Abrir mi panel',
            'closing' => 'Gracias por mantener tu cuenta al día.',
            'reason' => 'Recibiste este correo porque esta dirección quedó como el acceso de tu cuenta de Atendia.',
        ],

        'password_changed' => [
            'subject' => 'Tu contraseña de Atendia cambió',
            'preheader' => 'Si fuiste tú, no hay nada que hacer.',
            'eyebrow' => 'Aviso de seguridad',
            'title' => 'Tu contraseña cambió',
            'intro' => 'Hola :name, la contraseña de tu cuenta se cambió el :time (UTC).',
            'body' => 'Si fuiste tú, no hay nada que hacer.',
            'alert' => 'Si no fuiste tú, restablece tu contraseña ahora mismo: vas a cerrar cualquier acceso que no reconozcas.',
            'cta' => 'Restablecer mi contraseña',
            'closing' => 'Cuidar tu cuenta también es atenderte bien.',
            'reason' => 'Recibiste este correo porque la contraseña de tu cuenta de Atendia cambió.',
        ],

        'closed' => [
            'subject' => 'Cerramos tu cuenta de Atendia',
            'preheader' => 'Tienes :days días para volver con todo tal como estaba.',
            'eyebrow' => 'Cuenta cerrada',
            'title' => 'Tu cuenta quedó cerrada',
            'intro' => 'Hola :name, cerramos tu cuenta y tu asistente dejó de atender.',
            'body' => 'Guardamos todo tal como estaba durante :days días: si cambias de idea, vuelves con un clic o simplemente iniciando sesión.',
            'alert' => 'Si no fuiste tú, restaura tu cuenta ahora y cambia tu contraseña.',
            'cta' => 'Restaurar mi cuenta',
            'closing' => 'Gracias por el tiempo que atendimos juntos.',
            'reason' => 'Recibiste este correo porque se cerró tu cuenta de Atendia.',
        ],
    ],

    'billing' => [
        'eyebrow' => 'Tu plan',
        'cta' => 'Ir a Mis pagos',
        'cta_history' => 'Ver mis pagos',
        'closing' => 'Gracias por seguir atendiendo con nosotros.',
        'reason' => 'Recibiste este correo porque es el correo de facturación de tu negocio en Atendia.',

        'upcoming' => [
            'subject' => 'Tu próximo pago es en :days días',
            'preheader' => 'Págalo antes para que tu asistente siga atendiendo sin cortes.',
            'title' => 'Tu próximo pago es en :days días',
            'intro' => 'Hola :name, el :date se renueva tu plan :plan. Este es el monto:',
            'body' => 'Págalo antes de esa fecha y sube el comprobante en Mis pagos: así tu asistente sigue atendiendo sin cortes.',
        ],

        'overdue' => [
            'subject' => 'Tu pago está vencido: te quedan :days días',
            'preheader' => 'Tu asistente sigue atendiendo, pero no por mucho más.',
            'title' => 'Tu pago está vencido',
            'intro' => 'Hola :name, el pago del plan :plan venció el :date. Este es el monto:',
            'body' => 'Tu asistente sigue atendiendo :days días más. Si para entonces no se acredita el pago, se pausa hasta que lo recibamos. No se borra nada.',
        ],

        'paused' => [
            'subject' => 'Pausamos tu asistente por falta de pago',
            'preheader' => 'Apenas se acredite el pago, vuelve a atender.',
            'title' => 'Tu asistente está en pausa',
            'intro' => 'Hola :name, no recibimos el pago del plan :plan que vencía el :date. Este es el monto:',
            'body' => 'Tu asistente dejó de responder, pero todo quedó guardado: conversaciones, clientes y catálogo. Apenas se acredite el pago, vuelve a atender.',
        ],

        'paid' => [
            'subject' => 'Recibimos tu pago',
            'preheader' => 'Tu plan quedó al día.',
            'title' => 'Tu pago quedó acreditado',
            'intro' => 'Hola :name, acreditamos tu pago. Tu plan está al día hasta el :until.',
            'body' => 'El comprobante queda guardado en tu historial de pagos.',
        ],

        'rejected' => [
            'subject' => 'No pudimos acreditar tu pago',
            'preheader' => 'Revisa el motivo y vuelve a enviarlo.',
            'title' => 'No pudimos acreditar tu pago',
            'intro' => 'Hola :name, revisamos tu comprobante y no lo pudimos acreditar. Motivo: :reason',
            'body' => 'Sube un comprobante nuevo desde Mis pagos y lo revisamos enseguida.',
        ],
    ],

];
