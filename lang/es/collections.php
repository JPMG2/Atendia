<?php

declare(strict_types=1);

return [
    'title' => 'Cobranza',
    'sub' => 'A quién llamar hoy. Ordenado por lo que está en juego, no por quién debe hace más tiempo.',
    'read_at' => 'Al :time',

    'risk' => [
        'at_risk' => 'En riesgo por mes',
        'at_risk_hint' => 'Lo que dejás de cobrar si no vuelven',
        'owing' => 'Deben',
        'renewing' => 'Vencen en 7 días',
        'mrr' => 'Ingreso mensual',
        'vs_month' => 'USD :amount contra :month',
        'no_history' => 'Primer mes medido: todavía no hay con qué compararlo',
    ],

    'table' => [
        'business' => 'Negocio',
        'plan' => 'Plan',
        'monthly' => 'Por mes',
        'state' => 'Situación',
        'since' => 'Venció',
        'cycle' => 'Ciclo',
    ],

    'states' => [
        'grace' => 'En gracia',
        'paused' => 'Pausado',
        'renewing' => 'Vence pronto',
    ],

    'overdue' => '{1} hace 1 día|[2,*] hace :count días',
    'due_in' => '{0}hoy|{1} en 1 día|[2,*] en :count días',
    'cycle_monthly' => 'mensual',
    'cycle_yearly' => 'anual',

    'open_business' => 'Ver el negocio',

    'empty_title' => 'Nadie debe nada',
    'empty_body' => 'Ningún negocio está atrasado ni vence esta semana. Cuando alguno lo esté, va a aparecer acá con lo que está en juego.',
];
