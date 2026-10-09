<?php

declare(strict_types=1);

return [
    'title' => 'Adopción',
    'sub' => 'En qué paso se trabó cada negocio y a quién escribirle hoy.',
    'count' => '{0} Sin cuentas|{1} :count cuenta|[2,*] :count cuentas',

    'flow_title' => 'Por dónde se va la gente',
    'flow_hint' => 'Cuántas cuentas llegaron al menos hasta cada paso. En rojo, las que se quedaron ahí.',
    'flow_loss' => '{1} −:count no siguió|[2,*] −:count no siguieron',
    'flow_none' => 'sin pérdida',

    'list_title' => 'A quién escribirle',
    'list_hint' => 'La misma lista en tres vistas. Arriba, lo que más lleva esperando.',
    'tabs' => [
        'stalled' => 'Trabadas',
        'starting' => 'Arrancando',
        'active' => 'Activas',
    ],
    'legend' => [
        'stalled' => 'Una cuenta está trabada cuando pasa más tiempo del plazo en un mismo paso. El plazo se cambia en Ajustes de la plataforma.',
        'starting' => 'Cuentas que todavía están dentro del plazo de su paso: se miran, no se persiguen.',
        'active' => 'Cuentas cuyo asistente ya contestó.',
    ],

    'columns' => [
        'business' => 'Negocio',
        'stage' => 'Dónde se quedó',
        'idle' => 'Sin entrar',
        'why' => 'Por qué está acá',
    ],
    'since' => 'Desde el :date',
    'no_business' => 'Sin negocio',
    'never_returned' => 'Nunca volvió',
    'idle' => '{0} Entró hoy|{1} Hace :count día|[2,*] Hace :count días',
    'conversations_count' => '{0} sin conversaciones|{1} :count conversación|[2,*] :count conversaciones',

    'why' => [
        'registered' => 'Nunca creó su negocio',
        'business' => 'Creó el negocio y no cargó su catálogo',
        'catalog' => 'Cargó el catálogo y no conectó WhatsApp',
        'whatsapp' => 'Conectó WhatsApp y no recibió ningún mensaje',
        'conversation' => 'Recibió mensajes y su asistente no contestó',
        'answered' => 'Su asistente ya contesta: :conversations',
    ],
    'rule' => [
        'stalled' => '{0} Lleva menos de un día en este paso|{1} Lleva :count día en este paso (plazo: :limit)|[2,*] Lleva :count días en este paso (plazo: :limit)',
        'starting' => '{0} Lleva menos de un día en este paso|{1} Lleva :count día en este paso (aún dentro del plazo de :limit)|[2,*] Lleva :count días en este paso (aún dentro del plazo de :limit)',
    ],

    'actions' => [
        'view_business' => 'Ver ficha',
        'write' => 'Escribirle',
    ],
    'written' => [
        'you' => 'Le escribiste :when',
        'other' => 'Le escribió :name :when',
        'now' => 'hace un momento',
    ],

    'empty_title' => 'Todavía no hay cuentas',
    'empty_body' => 'Cuando un negocio se registre, acá aparece en qué paso se quedó.',
    'empty_tab' => [
        'stalled' => ['title' => 'Nadie trabado', 'body' => 'Todas las cuentas avanzan dentro del plazo.'],
        'starting' => ['title' => 'Nadie arrancando', 'body' => 'No hay cuentas dentro del plazo de su paso.'],
        'active' => ['title' => 'Todavía ninguna', 'body' => 'Cuando el asistente de un negocio conteste por primera vez, aparece acá.'],
    ],

    'mail' => [
        'subject' => '{1} Una cuenta se trabó|[2,*] :count cuentas se trabaron',
        'preheader' => 'Cuentas que pasaron el plazo de su paso sin avanzar.',
        'eyebrow' => 'Adopción',
        'intro' => 'Estas cuentas pasaron el plazo de su paso del alta y se quedaron trabadas.',
        'line' => ':business — :step · :days',
        'in_step' => '{0} menos de un día en el paso|{1} :count día en el paso (plazo :limit)|[2,*] :count días en el paso (plazo :limit)',
        'cta' => 'Ver las cuentas trabadas',
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
