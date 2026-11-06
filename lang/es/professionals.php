<?php

declare(strict_types=1);

return [
    'fields' => [
        'name' => 'Nombre',
        'capacity' => 'Turnos a la vez',
        'per_day' => 'Tope por día',
        'duration' => 'Duración',
        'price_type' => 'Tipo de precio',
        'price' => 'Precio',
        'deposit' => 'Seña',
    ],

    'notify' => [
        'saved' => ':name guardado.',
        'deleted' => 'Profesional eliminado.',
        'has_bookings' => 'No se puede eliminar: tiene turnos por venir. Desactívalo para que no reciba nuevos y elimínalo cuando los atienda.',
    ],
];
