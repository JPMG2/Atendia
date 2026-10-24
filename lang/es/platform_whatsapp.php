<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| WhatsApp de la plataforma (panel admin)
|--------------------------------------------------------------------------
|
| La línea propia de :brand: lo que sale por ella, su estado y cómo se vincula.
| Es de :brand y de ningún negocio. Voz operativa en tuteo.
|
*/

return [
    'title' => 'WhatsApp de la plataforma',
    'subtitle' => 'Lo que :brand dice por su cuenta: códigos de acceso, avisos al admin y resúmenes. Es de :brand y de ningún negocio.',
    'check' => 'Comprobar ahora',

    'status' => [
        'title' => 'Estado',
        'connected' => 'Conectado',
        'pairing' => 'Esperando el escaneo',
        'closed' => 'Sin vincular',
        'missing' => 'Sin crear',
        'unreachable' => 'Sin respuesta',
        'not_configured' => 'Sin instancia',
        'checking' => 'Comprobando…',
    ],

    'rows' => [
        'number' => 'Número',
        'number_none' => 'Sin número vinculado',
        'instance' => 'Instancia',
        'checked' => 'Última lectura',
        'since_closed' => 'Desde',
        'since_connected' => 'Conectado desde',
    ],

    'reason' => [
        'device_removed' => 'se desvinculó desde el teléfono',
        'other' => 'se cerró la sesión',
    ],

    'notes' => [
        'connected' => 'Los códigos de acceso y los avisos salen por WhatsApp. Si se cae, el tablero lo marca en rojo y los códigos vuelven a salir por correo.',
        'down' => 'Mientras no esté vinculada, los códigos de acceso salen por correo y los avisos al admin no salen.',
        'unreachable' => 'No se pudo preguntar al puente de WhatsApp. No se afirma nada de la línea hasta que conteste.',
        'not_configured' => 'Falta nombrar la instancia en la configuración del servidor. Sin ella nada sale por WhatsApp.',
    ],

    'link' => [
        'title' => 'Vincular',
        'sub' => 'Escanéalo con el teléfono de la línea de :brand. El código cambia solo.',
        'start' => 'Vincular este número',
        'again' => 'Generar otro código',
        'qr_alt' => 'Código QR para vincular el WhatsApp de la plataforma',
        'renews' => 'Se renueva en',
        'steps' => [
            'open' => 'Abre WhatsApp en el teléfono de la línea de :brand',
            'open_number' => 'Abre WhatsApp en el teléfono del número :number',
            'devices' => 'Ve a Dispositivos vinculados y toca Vincular un dispositivo',
            'scan' => 'Escanea este código',
        ],
        'linked' => 'WhatsApp de la plataforma vinculado',
        'failed' => 'No se pudo pedir el código. Revisa que el puente de WhatsApp responda.',
    ],

    'unlink' => [
        'title' => 'Desvincular',
        'body' => 'Cierra la sesión de WhatsApp de este número. Hasta que lo vincules otra vez, los códigos de acceso salen por correo y los avisos al admin no salen.',
        'action' => 'Desvincular este número',
        'confirm_title' => '¿Desvincular el WhatsApp de la plataforma?',
        'confirm_message' => 'Es lo que sale por esta cuenta: códigos de acceso, avisos críticos al admin y el resumen diario. Se vuelve a vincular escaneando un QR.',
        'confirm_accept' => 'Desvincular',
        'done' => 'WhatsApp de la plataforma desvinculado',
        'failed' => 'No se pudo desvincular. Revisa que el puente de WhatsApp responda.',
    ],

    'uses' => [
        'title' => 'Qué sale por esta cuenta',
        'codes' => 'Códigos de acceso',
        'codes_body' => 'El código de cada inicio de sesión desde un dispositivo nuevo, en el panel y en la app.',
        'alerts' => 'Avisos críticos al admin',
        'alerts_body' => 'Lo que no puede esperar: un pago que falla, un servicio caído, un negocio suspendido.',
        'digest' => 'Resumen diario',
        'digest_body' => 'Lo que pasó en la plataforma, a la hora de cierre.',
    ],

    'guard' => [
        'title' => 'Ningún negocio puede usarla ni borrarla',
        'body' => 'Quitar un negocio nunca toca esta cuenta, y esta instancia no se ofrece como «sin dueño» para liberarla. Vincular y desvincular piden un permiso propio, que Soporte no tiene.',
    ],
];
