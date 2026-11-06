<?php

declare(strict_types=1);

// Venezuela: "cita" instead of "turno"; everything else falls back to lang/es.

return [
    'sub' => 'Quién atiende tus citas. Cada profesional tiene sus días, sus horas y su cupo; lo que no cargues, lo hereda de tu negocio.',

    'needs_services' => [
        'body' => 'Marca en Servicios cuáles se reservan con cita: sin eso, un profesional no tiene qué atender.',
    ],

    'list' => [
        'upcoming' => '{1} 1 cita por venir|[2,*] :count citas por venir',
        'empty_body' => 'Mientras no cargues ninguno, tu agenda funciona como una sola. Agrega uno para que cada persona tenga sus horas y sus citas.',
    ],

    'sheet' => [
        'active' => 'Recibe citas nuevas',
        'active_hint' => 'Desactivado no recibe citas nuevas. Las que ya tiene se conservan.',
        'delete_message' => 'Se quita de las listas y de la agenda. Sus citas pasadas quedan en el historial.',
    ],

    'fields' => [
        'capacity' => 'Citas a la vez',
    ],

    'closures' => [
        'booked' => '{1}Tiene 1 cita en esas fechas: muévela o cancélala desde la Agenda y vuelve a cargar el cierre.|[2,*]Tiene :count citas en esas fechas: muévelas o cancélalas desde la Agenda y vuelve a cargar el cierre.',
    ],

    'notify' => [
        'has_bookings' => 'No se puede eliminar: tiene citas por venir. Desactívalo para que no reciba nuevas y elimínalo cuando las atienda.',
    ],
];
