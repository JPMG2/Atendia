<?php

declare(strict_types=1);

return [

    'businesses' => [
        'title' => 'Negocios',
        'sub' => 'Todos los que atiende la plataforma, con su plan y su plata.',
        'empty' => 'Todavía no hay ningún negocio dado de alta.',
        'count' => ':count negocio|:count negocios',

        'name' => 'Negocio',
        'plan' => 'Plan',
        'state' => 'Estado',
        'due' => 'Vence',
        'owes' => 'Debe',
        'open' => 'Ver la ficha',
        'close' => 'Cerrar la ficha',

        'states' => [
            'trialing' => 'En prueba',
            'active' => 'Al día',
            'past_due' => 'En gracia',
            'paused' => 'Pausado',
            'suspended' => 'Suspendido',
            'canceling' => 'Se da de baja',
            'ended' => 'Dado de baja',
            'none' => 'Sin plan',
        ],

        'card' => [
            'plan' => 'Plan y cobro',
            'cycle' => 'Ciclo',
            'monthly' => 'Mensual',
            'yearly' => 'Anual',
            'next_amount' => 'Próximo cobro',
            'next_date' => 'Próximo vencimiento',
            'payments' => 'Últimos pagos',
            'payments_empty' => 'Todavía no pagó nada.',
            'date' => 'Fecha',
            'concept' => 'Concepto',
            'amount' => 'Monto',
            'status' => 'Estado',
        ],

        'cancel' => [
            'title' => 'Baja',
            'hint' => 'Sigue andando hasta el :date, que es lo que ya pagó.',
            'hint_no_date' => 'No tiene un período pagado en curso.',
            'action' => 'Registrar la baja',
            'accept' => 'Registrar la baja',
            'confirm' => '¿Registrás la baja de :name? Su asistente sigue respondiendo hasta el :date.',
            'confirm_no_date' => '¿Registrás la baja de :name?',
            'asked_on' => 'Pidió la baja el :date.',
            'ends_on' => 'Termina el :date.',
            'ended_on' => 'Terminó el :date.',
            'undo' => 'Deshacer la baja',
            'undo_confirm' => '¿Deshacés la baja de :name? Vuelve a ser un cliente normal.',
            'done' => 'Baja registrada.',
            'undone' => 'Baja deshecha.',
            'no_subscription' => 'Este negocio no tiene plan: no hay baja que registrar.',
        ],
    ],

    'home' => [
        'title' => 'Inicio',
        'sub' => 'Lo que está esperando una decisión tuya, ahora mismo.',
        'as_of' => 'Al :date',

        'tiles' => [
            'receipts' => 'Comprobantes por verificar',
            'receipts_none' => 'Nada por verificar',
            'receipts_order' => 'El que pagó primero, primero',
            'receipts_waiting' => 'El más viejo espera hace :days día|El más viejo espera hace :days días',
            'receipts_waiting_today' => 'El más viejo llegó hoy',
            'mrr_collected' => 'Cobrado en 30 días: USD :amount',
            'leaving' => 'Bajas programadas',
            'leaving_none' => 'Nadie se está yendo',
            'leaving_amount' => 'Se deja de cobrar USD :amount por mes',
            'plan_changes' => 'Cambios de plan por verificar',
            'plan_changes_hint' => 'De esos comprobantes, los que mueven de plan',
            'mrr' => 'Ingreso mensual recurrente',
            'mrr_from' => ':count suscripciones pagando',
            'mrr_none' => 'Todavía nadie paga: :count en prueba',
        ],

        'queue' => [
            'title' => 'Comprobantes esperando',
            'sub' => 'Acreditá acá mismo. Rechazar pide un motivo y se hace en Pagos.',
            'empty' => 'No hay comprobantes esperando.',
            'date' => 'Llegó',
            'business' => 'Negocio',
            'plan' => 'Plan',
            'amount' => 'Monto',
            'action' => 'Acción',
            'approve' => 'Acreditar',
            'see_all' => 'Ver los :count en Pagos',
        ],

        'renewals' => [
            'title' => 'Renovaciones de esta semana',
            'sub' => 'Lo que vence en los próximos 7 días, lo más cerca primero.',
            'vs_last_week' => 'La semana pasada se cobró USD :amount',
            'expected' => 'Se esperan USD :amount',
            'empty' => 'Nada vence esta semana.',
            'business' => 'Negocio',
            'plan' => 'Plan',
            'cycle' => 'Ciclo',
            'due' => 'Vence',
            'amount' => 'Monto',
            'total' => 'Total de la semana',
            'monthly' => 'Mensual',
            'yearly' => 'Anual',
            'trial' => 'En prueba',
        ],

        'leaving' => [
            'title' => 'Bajas programadas',
            'sub' => 'Pidieron la baja y siguen andando hasta la fecha que ya pagaron.',
            'empty' => 'Ningún negocio pidió la baja.',
            'business' => 'Negocio',
            'plan' => 'Plan',
            'amount' => 'Monto',
            'ends' => 'Termina',
            'asked' => 'Pidió',
        ],

        'struggling' => [
            'title' => 'Vencidos y pausados',
            'sub' => 'No pagaron: en gracia, o ya con el asistente callado.',
            'empty' => 'Ningún negocio vencido ni pausado.',
            'business' => 'Negocio',
            'plan' => 'Plan',
            'state' => 'Estado',
            'since' => 'Venció',
            'past_due' => 'En gracia',
            'paused' => 'Pausado',
        ],
    ],

];
