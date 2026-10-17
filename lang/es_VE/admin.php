<?php

declare(strict_types=1);

// Venezuela: "cita" instead of "turno"; everything else falls back to lang/es.

return [
    'settings' => [
        'keys' => [
            'schedule' => [
                'appointment_reminder_hours' => [
                    'label' => 'Recordatorio de cita',
                    'hint' => 'Cuántas horas antes de la cita se le avisa al cliente.',
                ],
            ],
        ],
    ],
];
