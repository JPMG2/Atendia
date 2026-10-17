<?php

declare(strict_types=1);

// Venezuela: "cita" instead of "turno"; everything else falls back to lang/es.

return [
    'sub' => 'Las citas del día y las horas que todavía están libres.',
    'book' => 'Reservar una cita',
    'week_none' => 'Sin citas',
    'plain_slot' => 'Cita general',
    'cancel' => 'Cancelar la cita',
    'cancel_title' => '¿Cancelar esta cita?',
    'cancel_accept' => 'Cancelar la cita',
    'off' => [
        'title' => 'Las citas están apagadas',
        'body' => 'Por eso no hay horas para ofrecer: no es que el día esté lleno. Enciéndelas en Mi negocio · Citas y tu asistente empieza a dar hora por WhatsApp.',
        'cta' => 'Activar las citas',
    ],
    'empty' => [
        'title' => 'No hay citas este día',
    ],
    'free' => [
        'sub' => 'Las que tu asistente ofrece ese día. Toca una para anotar una cita.',
    ],
    'form' => [
        'title' => 'Reservar una cita',
        'service_placeholder' => 'Sin servicio: cita general',
    ],
    'public' => [
        'title' => 'Reserva tu cita',
        'submit' => 'Reservar mi cita',
    ],
    'closure_notice' => '*:business*: tenemos que cancelar tu cita del :when porque ese día no abrimos. Escríbenos y lo reprogramamos.',
    'closure_notice_reason' => '*:business*: tenemos que cancelar tu cita del :when porque ese día no abrimos (:reason). Escríbenos y lo reprogramamos.',
    'notify' => [
        'booked' => 'Cita reservada.',
        'moved' => 'La cita quedó en su nuevo horario.',
        'cancelled' => 'Cita cancelada: la hora volvió a quedar libre.',
        'marked' => 'Cita marcada como atendida.',
        'slot_taken' => 'No pudimos guardar la cita: esa hora ya no está libre.',
    ],
];
