<?php

declare(strict_types=1);

return [
    'title' => 'WhatsApp',
    'sub' => 'Conecta el número que atiende tu asistente.',
    'no_business' => 'Primero crea tu negocio y después conectamos tu WhatsApp.',
    'no_business_cta' => 'Crear mi negocio',
    'topbar_connected' => 'WhatsApp conectado',
    'topbar_disconnected' => 'WhatsApp sin conectar',
    'topbar_unverified' => 'WhatsApp sin verificar',
    'topbar_connected_as' => 'Conectado como :name',
    'topbar_verified_ago' => 'Verificado :ago',

    'connected' => [
        'tag' => 'Conectado',
        'title' => 'Tu WhatsApp está conectado',
        'body' => 'Tu asistente responde por este número con tu oferta al día.',
        'since' => 'Atendiendo desde el',
        'no_profile_name' => 'Sin nombre de perfil',
        'identity_hint' => 'Este es el número que quedó vinculado. Si no es el de tu negocio, desconéctalo y vincula el correcto.',
    ],

    'disconnect' => [
        'button' => 'Desconectar este número',
        'title' => 'Desconectar tu WhatsApp',
        'message' => 'Tu asistente deja de responder por este número hasta que vincules uno con el código QR. Las conversaciones guardadas no se borran.',
        'accept' => 'Desconectar el número',
        'done' => 'Listo. El número quedó desconectado; vincula el correcto con el código QR.',
        'failed' => 'No pudimos desconectar el número ahora. Intenta de nuevo en un momento.',
    ],

    'lost_phone' => [
        'title' => '¿Te robaron o perdiste el teléfono?',
        'body' => 'Pausa tu WhatsApp y nadie puede usarlo a través de :brand hasta que lo reactives con tu contraseña nueva. No es dar de baja: no se borra nada.',
        'effects' => [
            'Tu asistente deja de responder y no sale ningún mensaje ni recordatorio por tu número.',
            'El número se desvincula: cuando lo recuperes, lo vuelves a vincular con el código QR.',
            'Se cierran todas las sesiones y la app, también las de tu equipo.',
            'Los avisos y los códigos de acceso dejan de ir a tu WhatsApp: te llegan por correo.',
        ],
        'billing' => 'Tu plan y tus fechas de cobro siguen igual.',
        'password_hint' => 'Elige una contraseña nueva: la que quedó guardada en el teléfono deja de servir. Si tu correo también estaba abierto en ese teléfono, cámbiale la contraseña desde su propia página.',
        'button' => 'Pausar mi WhatsApp',
        'confirm_title' => 'Pausar tu WhatsApp',
        'confirm_message' => 'Tu asistente deja de atender, se desvincula el número, cambia tu contraseña y se cierran todas las sesiones y la app. Para reactivarlo vas a necesitar la contraseña nueva y volver a escanear el código QR.',
        'confirm_accept' => 'Pausar mi número',
        'paused' => 'Listo. Tu WhatsApp quedó en pausa y nada sale por tu número.',
        'paused_not_unlinked' => 'Tu WhatsApp quedó en pausa y nada sale por tu número, pero no pudimos desvincularlo ahora. Quítalo también desde Dispositivos vinculados cuando recuperes tu número.',
        'paused_tag' => 'En pausa',
        'paused_title' => 'Tu WhatsApp está en pausa',
        'paused_since' => 'Desde el',
        'paused_by' => 'la pausó :name',
        'resume_title' => '¿Ya recuperaste tu número?',
        'resume_body' => 'Escribe la contraseña que elegiste al pausar para reactivarlo. Después escanea el código QR desde el teléfono con tu número y tu asistente vuelve a atender.',
        'resume_button' => 'Reactivar mi WhatsApp',
        'resumed' => 'Listo. Ahora escanea el código QR para que tu asistente vuelva a atender.',
        'banner_title' => 'Tu WhatsApp está en pausa por robo o pérdida.',
        'banner_body' => 'Tu asistente no responde y nada sale por tu número hasta que lo reactives.',
        'banner_cta' => 'Reactivar',
        'cut_title' => 'Accesos que se cortaron',
        'cut_hint' => 'Quedaron fuera al pausar: la app y los navegadores de tu equipo que entraron en los últimos :days días. Tu contraseña ya cambió; si no reconoces un dispositivo de alguien de tu equipo, pídele que cambie la suya.',
        'cut_none' => 'No había dispositivos de tu equipo con acceso en los últimos :days días.',
        'cut_current' => 'Este equipo o uno igual',
        'cut_app' => 'App',
        'cut_last' => 'Último ingreso',
        'app_resume' => 'Para reactivarlo, entra a la web, en WhatsApp, con la contraseña nueva.',
    ],

    'history' => [
        'title' => 'Los últimos :days días',
        'since' => 'Desde el :date',
        'clean' => 'Tu número respondió sin interrupciones.',
        'outages' => 'Se desconectó',
        'times' => '{1}1 vez|[2,*]:count veces',
        'down' => 'y estuvo sin responder',
        'hours' => '{1}1 hora|[2,*]:count horas',
        'minutes' => '{0}menos de un minuto|{1}1 minuto|[2,*]:count minutos',
        'device_removed' => 'La última vez fue porque se desvinculó el dispositivo desde el teléfono: eso solo se arregla escaneando de nuevo.',
    ],

    'unverified' => [
        'tag' => 'Sin verificar',
        'title' => 'No pudimos comprobar tu conexión',
        'body' => 'El servicio que enlaza tu WhatsApp no respondió, así que preferimos no decirte un estado que no pudimos confirmar. Tus mensajes no se pierden: se entregan cuando vuelve.',
        'retry' => 'Volver a comprobar',
    ],

    'connect' => [
        'title' => 'Conecta tu WhatsApp',
        'body' => 'Vincula el número de tu negocio y tu asistente empieza a responder por ti, las 24 horas.',
        'steps' => [
            'Abre WhatsApp en tu teléfono',
            'Toca Dispositivos vinculados y luego Vincular un dispositivo',
            'Escanea el código de esta pantalla',
        ],
        'cta' => 'Conectar mi WhatsApp',
        'qr_alt' => 'Código QR para vincular tu WhatsApp',
        'qr_hint' => 'El código se renueva solo. No cierres esta pantalla.',
        'waiting' => 'Generando el código…',
        'cancel' => 'Cancelar',
        'done' => 'Listo. Tu asistente ya responde por ti.',
        'failed' => 'No pudimos conectar con WhatsApp. Intenta de nuevo en un momento.',
        'dedicated_title' => 'Usa un número exclusivo para tu negocio',
        'dedicated_body' => 'Si conectas tu número personal, el asistente también les responderá a tus familiares y amigos, y tus chats privados se mezclarán con los de tus clientes.',
        'tip_label' => 'Consejo',
        'tip' => '¿Tu número es nuevo? WhatsApp confía más en los números con historia: úsalo unos días de forma normal antes de conectarlo y arrancas con la mejor reputación.',
        'privacy' => 'Tus conversaciones son privadas: solo tu negocio las ve. Las usamos únicamente para que el asistente responda a tus clientes; nunca las vendemos ni las usamos para publicidad.',
    ],

    // The business app sends its own greeting and away messages from the phone: the assistant cannot see them.
    'business_app' => [
        'line' => 'Si usas WhatsApp Business, apaga el mensaje de saludo y el de ausencia: pueden salir a la vez que el asistente.',
        'reminder' => 'Si usas WhatsApp Business, revisa que el saludo y la ausencia de la app estén apagados.',
        'how' => 'Ver cómo',
        'hide' => 'Ocultar',
        'steps' => [
            'Abre WhatsApp Business y entra a Herramientas para la empresa.',
            'Abre Mensaje de saludo y desactívalo.',
            'Haz lo mismo con Mensaje de ausencia.',
        ],
    ],

    // Meta reviews the name a business shows. It is a warning, never a block.
    'name_rules' => [
        'title' => 'El nombre de tu negocio puede ser rechazado por Meta',
        'intro' => 'Cuando Meta revise el nombre que verán tus clientes, podría pedirte cambiarlo. Puedes conectar igual; esto es para que lo sepas antes:',
        'edit' => 'Cambiar el nombre',
        'same_brand' => 'Meta también pide que el nombre coincida con la marca real de tu negocio.',
        'too_short' => 'Tiene menos de 3 caracteres.',
        'all_caps' => 'Está todo en mayúsculas; Meta solo lo acepta en siglas.',
        'emoji' => 'Lleva emojis.',
        'url' => 'Parece una dirección web.',
        'whatsapp' => 'Incluye la palabra "WhatsApp", que Meta reserva para sí.',
    ],
];
