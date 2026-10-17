<?php

declare(strict_types=1);

// Venezuela: "cita" instead of "turno"; everything else falls back to lang/es.

return [
    'business' => [
        'hours' => [
            'closure_booked' => '{1} 1 cita reservada|[2,*] :count citas reservadas',
            'closure_notified' => '{0} No había a quién avisar|{1} Avisamos a 1 persona y su cita quedó cancelada|[2,*] Avisamos a :count personas y sus citas quedaron canceladas',
        ],
        'appointments' => [
            'title' => 'Citas',
            'sub' => 'Si tu negocio atiende con cita, tu asistente ofrece los horarios libres y los reserva solo.',
            'enabled' => 'Agenda de citas',
            'capacity' => 'Citas a la vez',
            'slot_minutes_hint' => 'Minutos de una cita, cuando el servicio no trae la suya.',
            'per_day_hint' => 'Máximo de citas en un día. Vacío = solo manda la capacidad.',
            'needs_services' => 'Marca en Servicios cuáles se reservan con cita.',
            'ready' => '{1} 1 servicio se reserva con cita.|[2,*] :count servicios se reservan con cita.',
            'saved' => 'Citas actualizados.',
        ],
    ],
    'assistant' => [
        'handoff_rules_placeholder' => 'Si describe síntomas o pide un diagnóstico
Si quiere cambiar una cita ya pagada',
    ],
];
