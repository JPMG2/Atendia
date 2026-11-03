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
            'reminder_held' => 'No se pudo enviar el recordatorio de una cita',
        ],
    ],
    'bundles' => [
        'appointment_booked' => ':count citas nuevas',
        'closure_notice_held' => ':count clientes por llamar: su cita sigue en pie en un día que no abres',
        'reminder_held' => ':count recordatorios de cita sin enviar por WhatsApp',
    ],
    'lines' => [
        'appointment_booked' => 'Cita nueva de :name: :when, :service',
        'appointment_booked_plain' => 'Cita nueva de :name: :when',
        'closure_notice_held' => 'No se pudo avisar a :name por WhatsApp: su cita del :when sigue en pie en un día que no abres',
        'reminder_held' => 'El recordatorio de la cita de :name (:when) no se envió: todavía no le escribió a tu WhatsApp',
        'reminder_held_ignored' => 'El recordatorio de la cita de :name (:when) no se envió: no respondió tus últimos mensajes',
    ],
];
