<?php

declare(strict_types=1);

// Venezuela: "cita" instead of "turno"; everything else falls back to lang/es.

return [
    'business' => [
        'hours' => [
            'closure_booked' => '{1} 1 cita reservada|[2,*] :count citas reservadas',
            'closure_notified' => '{0} No había a quién avisar.|{1} Estamos avisando a 1 persona por WhatsApp; su cita se cancela cuando sale el aviso.|[2,*] Estamos avisando a :count personas por WhatsApp, de a una para cuidar tu número; cada cita se cancela cuando sale su aviso.',
            'closure_unreachable' => '{1} A 1 persona no le escribimos para cuidar tu número (nunca te escribió, no responde, está bloqueada o llegaste al tope de hoy): su cita sigue en pie, llámala.|[2,*] A :count personas no les escribimos para cuidar tu número (nunca te escribieron, no responden, están bloqueadas o llegaste al tope de hoy): sus citas siguen en pie, llámalas.',
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
            'saved' => 'Citas actualizadas.',
        ],
    ],
    'assistant' => [
        'handoff_rules_placeholder' => 'Si describe síntomas o pide un diagnóstico
Si quiere cambiar una cita ya pagada',
    ],
];
