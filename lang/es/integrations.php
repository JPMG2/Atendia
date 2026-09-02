<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Integraciones (panel admin)
|--------------------------------------------------------------------------
|
| La salud de todo lo que AtendIa consume. Base neutra (tuteo); los hints
| están escritos en impersonal a propósito, así `es_AR` no necesita override.
|
*/

return [
    'title' => 'Integraciones',
    'subtitle' => 'La salud de todo lo que AtendIa tiene conectado: si está prendido, si responde y dónde mirar cuando algo falla.',

    'refresh' => 'Actualizar todo',
    'recheck' => 'Volver a probar',
    'checking' => 'Comprobando…',
    'latency' => ':ms ms',

    'status' => [
        'connected' => 'Conectada',
        'failing' => 'Con fallas',
        'off' => 'Apagada',
    ],

    'names' => [
        'database' => 'Base de datos',
        'redis' => 'Redis',
        'mail' => 'Correo',
        'whatsapp' => 'WhatsApp (Evolution API)',
        'n8n' => 'n8n',
        'chatwoot' => 'Chatwoot',
        'openai' => 'OpenAI',
        'reverb' => 'Tiempo real (Reverb)',
    ],

    'descriptions' => [
        'database' => 'Donde viven todos los datos.',
        'redis' => 'Cola de trabajos, sesiones y caché.',
        'mail' => 'Por dónde salen los correos del sistema.',
        'whatsapp' => 'El canal por el que la IA atiende a los clientes.',
        'n8n' => 'Los flujos que automatizan la operación.',
        'chatwoot' => 'La bandeja de conversaciones del equipo.',
        'openai' => 'El proveedor de la inteligencia del asistente.',
        'reverb' => 'Las actualizaciones en vivo del panel.',
    ],

    'detail' => [
        'answering' => 'Respondiendo con normalidad.',
        'version' => 'Versión :version, respondiendo.',
        'database' => 'Conexión :name activa.',
        'mail' => 'SMTP :host::port aceptando conexiones.',
        'mail_off' => 'El mailer configurado es ":mailer", no hay servidor que probar.',
        'reverb' => 'Puerto :port aceptando conexiones.',
        'not_configured' => 'Sin configurar en el entorno.',
        'port_closed' => 'El puerto :host::port no acepta conexiones.',
        'unknown' => 'Integración desconocida.',
    ],

    'hint' => [
        'database' => 'Sin base de datos no funciona nada: conviene mirar el contenedor de Postgres antes que cualquier otra cosa.',
        'redis' => 'El contenedor de Redis puede estar caído; los trabajos en cola quedan frenados hasta que vuelva.',
        'mail' => 'El servidor de correo no acepta conexiones: los correos del sistema van a quedar sin salir.',
        'whatsapp' => 'La Evolution API no responde: el contenedor puede estar apagado o la clave puede haber cambiado. Sin ella, la IA no recibe mensajes de WhatsApp.',
        'n8n' => 'n8n no responde: los flujos automatizados están detenidos hasta que el contenedor vuelva.',
        'chatwoot' => 'Chatwoot no responde: la bandeja del equipo queda inaccesible, aunque la IA sigue atendiendo.',
        'openai' => 'OpenAI no responde desde este servidor: puede ser la clave, el saldo de la cuenta o la salida a internet. El asistente no puede pensar sin esto.',
        'reverb' => 'El proceso de Reverb no está escuchando; supervisor debería levantarlo solo. Sin él, el panel deja de actualizarse en vivo.',
    ],
];
