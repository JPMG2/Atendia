<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| llms.txt
|--------------------------------------------------------------------------
| The words around the figures. The figures themselves (prices, caps, days)
| come from the plans table and the company row, never from here.
*/

return [
    'audience' => 'Para negocios que atienden a sus clientes por WhatsApp y quieren responder siempre, sin contratar a nadie más.',

    'plans' => [
        'title' => 'Planes y precios',
        'currency' => 'Precios en :currency por mes.',
        'line' => ':name: :price al mes (:yearly al mes con pago anual). :desc',
        'includes' => 'Incluye: :lines.',
    ],

    'links' => [
        'title' => 'Enlaces',
        'home' => 'Inicio',
        'home_note' => 'qué hace y cuánto cuesta',
        'register' => 'Crear una cuenta',
        'register_note' => 'empezar la prueba gratis',
        'login' => 'Ingresar',
        'login_note' => 'acceso de quien ya tiene cuenta',
    ],

    'contact' => [
        'title' => 'Contacto',
        'email' => 'Correo',
        'whatsapp' => 'WhatsApp',
        'web' => 'Sitio web',
    ],
];
