<?php

declare(strict_types=1);

// Venezuela: "cita" instead of "turno"; everything else falls back to lang/es.

return [
    'digest' => [
        'appointment_booked' => '{1}1 cita nueva|[2,*]:count citas nuevas',
    ],
    'empty' => [
        'body' => 'Acá aparece lo que necesita tu atención: un cliente esperando respuesta, una cita nueva o tu WhatsApp caído.',
    ],
    'settings' => [
        'kinds' => [
            'appointment_booked' => 'Se reservó una cita nueva',
        ],
    ],
    'bundles' => [
        'appointment_booked' => ':count citas nuevas',
    ],
    'lines' => [
        'appointment_booked' => 'Cita nueva de :name: :when, :service',
        'appointment_booked_plain' => 'Cita nueva de :name: :when',
    ],
];
