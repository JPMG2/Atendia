<?php

declare(strict_types=1);

return [
    'title' => 'Adopción',
    'sub' => 'En qué paso se quedó cada negocio, y hace cuánto no entra.',
    'count' => '{0} Sin cuentas|{1} :count cuenta|[2,*] :count cuentas',

    'funnel_title' => 'Dónde se queda la gente',
    'legs_title' => 'Cuánto tarda en llegar a cada paso',
    'leg_days' => '{0} el mismo día|{1} :count día|[2,*] :count días',
    'not_yet' => 'Todavía no',
    'filter' => 'Mostrar',
    'filter_stalled' => 'Quedaron en el camino',
    'filter_all' => 'Todas las cuentas',

    'step' => 'Paso :position de :total',
    'no_business' => 'Sin negocio',
    'registered' => 'Se registró :when',
    'never_returned' => 'No volvió a entrar',
    'idle' => '{0} Entró hoy|{1} Hace :count día|[2,*] Hace :count días',
    'conversations' => '{1} conversación|[2,*] conversaciones',
    'tickets' => '{1} reporte|[2,*] reportes',

    'empty_title' => 'Todavía no hay cuentas',
    'empty_body' => 'Cuando un negocio se registre, acá aparece en qué paso se quedó.',
    'done_title' => 'Nadie se quedó en el camino',
    'done_body' => 'Todas las cuentas llegaron hasta la primera respuesta de su asistente.',

    'mail' => [
        'subject' => '{1} Una cuenta se quedó en el camino|[2,*] :count cuentas se quedaron en el camino',
        'preheader' => 'Negocios que no volvieron a entrar antes de que su asistente contestara.',
        'eyebrow' => 'Adopción',
        'intro' => 'Estos negocios dejaron de entrar antes de que su asistente contestara por primera vez.',
        'line' => ':business — :step · :days',
        'cta' => 'Ver la adopción',
        'closing' => 'Una llamada a tiempo recupera más cuentas que cualquier pantalla.',
    ],

    'steps' => [
        'registered' => 'Se registró',
        'business' => 'Creó el negocio',
        'catalog' => 'Cargó su catálogo',
        'whatsapp' => 'Conectó WhatsApp',
        'conversation' => 'Recibió el primer mensaje',
        'answered' => 'Su asistente contestó',
    ],
];
