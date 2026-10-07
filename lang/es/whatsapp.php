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
];
