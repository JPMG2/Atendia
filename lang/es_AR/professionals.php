<?php

declare(strict_types=1);

// Argentina: voseo where the neutral base tutea; everything else falls back to lang/es.

return [
    'no_business' => [
        'body' => 'Tus profesionales se suman a un negocio. En cuanto termines de crearlo, acá los cargás.',
    ],

    'needs_services' => [
        'body' => 'Marcá en Servicios cuáles se reservan con turno: sin eso, un profesional no tiene qué atender.',
    ],

    'list' => [
        'empty_body' => 'Mientras no cargues ninguno, tu agenda funciona como una sola. Agregá uno para que cada persona tenga sus horas y sus turnos.',
    ],

    'hours' => [
        'hint' => 'Si no cargás los suyos, atiende en el horario del negocio.',
    ],

    'services' => [
        'hint' => 'Marcá los que hace. Si para esta persona cambia la duración o el precio, cargalos; vacío usa los del servicio.',
    ],

    'notify' => [
        'has_bookings' => 'No se puede eliminar: tiene turnos por venir. Desactivalo para que no reciba nuevos y eliminalo cuando los atienda.',
    ],
];
