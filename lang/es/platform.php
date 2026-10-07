<?php

declare(strict_types=1);

return [
    'new_business' => [
        'subject' => 'Se registró :business',
        'preheader' => 'Un negocio nuevo acaba de entrar.',
        'eyebrow' => 'Alta',
        'intro' => 'Entró un negocio nuevo. Los primeros días deciden si se queda.',
        'line' => ':business — :email · :country',
        'no_country' => 'sin país cargado',
        'cta' => 'Ver el negocio',
        'closing' => 'Una llamada el primer día recupera más cuentas que cualquier pantalla.',
    ],

    'first_payment' => [
        'subject' => ':business pasó de prueba a pago',
        'preheader' => 'Una prueba se convirtió en cliente.',
        'eyebrow' => 'Primer pago',
        'intro' => 'Dejó de probar y empezó a pagar. Es la conversión que cuenta.',
        'line' => ':business — plan :plan · :cycle · :amount',
        'cycle_monthly' => 'mensual',
        'cycle_yearly' => 'anual',
        'cta' => 'Ver los pagos',
        'closing' => 'El que paga la primera vez mira el servicio con otros ojos: es el momento de preguntarle cómo le fue.',
    ],
];
