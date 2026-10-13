<?php

declare(strict_types=1);

// Voseo overrides of lang/es/admin.php: the base is neutral (tuteo), and the
// owner's own panel keeps reading in the form she speaks.
return [
    'ai' => [
        'bulk' => ['placeholder' => 'Elegí un modelo'],
        'models_empty' => 'Cargá el primero con su precio y desde qué día rige.',
    ],

    'roles' => [
        'panel_implicit' => 'Entrar al panel va incluido en todo rol: lo que elegís es qué ve adentro.',
    ],

    'settings' => [
        'keys' => [
            'costs' => [
                'fixed_monthly_usd' => [
                    'hint' => 'Servidor, WhatsApp, dominio y lo demás que pagás por mes, en dólares y sin contar la IA. En cero, el resultado neto no se muestra.',
                ],
            ],
        ],
    ],

    'ai_usage' => [
        'result' => ['net_missing' => 'Cargá los costos fijos del mes en Ajustes para ver el neto.'],
    ],

    'businesses' => [
        'cancel' => [
            'confirm' => '¿Registrás la baja de :name? Su asistente sigue respondiendo hasta el :date.',
            'confirm_no_date' => '¿Registrás la baja de :name?',
            'undo_confirm' => '¿Deshacés la baja de :name? Vuelve a ser un cliente normal.',
        ],
    ],

    'home' => [
        'queue' => ['sub' => 'Acreditá acá mismo. Rechazar pide un motivo y se hace en Pagos.'],
    ],
];
