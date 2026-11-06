<?php

declare(strict_types=1);

return [
    'title' => 'Profesionales',
    'sub' => 'Quién atiende tus turnos. Cada profesional tiene sus días, sus horas y su cupo; lo que no cargues, lo hereda de tu negocio.',

    'no_business' => [
        'title' => 'Primero creamos tu negocio',
        'body' => 'Tus profesionales se suman a un negocio. En cuanto termines de crearlo, acá los cargas.',
        'cta' => 'Crear mi negocio',
    ],

    'needs_services' => [
        'body' => 'Marca en Servicios cuáles se reservan con turno: sin eso, un profesional no tiene qué atender.',
        'cta' => 'Ir a Servicios',
    ],

    'list' => [
        'title' => 'Tus profesionales',
        'new' => 'Agregar profesional',
        'edit' => 'Editar a :name',
        'empty_title' => 'Todavía no hay profesionales',
        'empty_body' => 'Mientras no cargues ninguno, tu agenda funciona como una sola. Agrega uno para que cada persona tenga sus horas y sus turnos.',
        'inactive' => 'Desactivado',
        'away' => 'Ausente hasta el :until',
        'services' => '{0} Sin servicios|{1} 1 servicio|[2,*] :count servicios',
        'business_hours' => 'Sigue el horario del negocio',
        'limits' => 'Hasta :per_day por día',
        'upcoming' => '{1} 1 turno por venir|[2,*] :count turnos por venir',
    ],

    'sheet' => [
        'new_title' => 'Nuevo profesional',
        'edit_title' => 'Editar a :name',
        'save' => 'Guardar',
        'cancel' => 'Cancelar',
        'active' => 'Recibe turnos nuevos',
        'active_on' => 'Activo',
        'active_off' => 'Desactivado',
        'active_hint' => 'Desactivado no recibe turnos nuevos. Los que ya tiene se conservan.',
        'delete' => 'Eliminar profesional',
        'delete_title' => 'Eliminar a :name',
        'delete_message' => 'Se quita de las listas y de la agenda. Sus turnos pasados quedan en el historial.',
        'upcoming_note' => 'Tiene :upcoming, así que no se puede eliminar todavía. Desactívalo para que no reciba nuevos.',
    ],

    'fields' => [
        'name' => 'Nombre',
        'capacity' => 'Turnos a la vez',
        'capacity_hint' => 'Vacío usa el del negocio.',
        'per_day' => 'Tope por día',
        'per_day_hint' => 'Vacío usa el del negocio.',
        'duration' => 'Duración',
        'price_type' => 'Tipo de precio',
        'price' => 'Precio',
        'deposit' => 'Seña',
    ],

    'hours' => [
        'label' => 'Días y horas',
        'hint' => 'Si no cargas los suyos, atiende en el horario del negocio.',
        'use_business' => 'Horario del negocio',
        'switch_on' => 'Sigue el del negocio',
        'switch_off' => 'Tiene el suyo',
        'opens' => 'Abre',
        'closes' => 'Cierra',
        'closed' => 'No atiende',
    ],

    'services' => [
        'label' => 'Servicios que atiende',
        'hint' => 'Marca los que hace. Si para esta persona cambia la duración o el precio, cárgalos; vacío usa los del servicio.',
        'same' => 'Como el servicio',
    ],

    'closures' => [
        'title' => 'Vacaciones y cierres',
        'hint' => 'Los días que esta persona no atiende aunque el negocio abra. Se guardan al agregarlos, no con Guardar.',
        'save_first' => 'Guarda al profesional para cargarle vacaciones.',
        'dates' => 'Días',
        'reason' => 'Motivo',
        'reason_hint' => 'Opcional. Para que lo recuerdes tú.',
        'add' => 'Agregar',
        'empty' => 'Sin cierres cargados.',
        'remove' => 'Quitar el cierre del :days',
        'added' => 'Cierre cargado.',
        'exists' => 'Esos días ya estaban cargados.',
        'removed' => 'Cierre quitado.',
        'booked' => '{1}Tiene 1 turno en esas fechas: muévelo o cancélalo desde la Agenda y vuelve a cargar el cierre.|[2,*]Tiene :count turnos en esas fechas: muévelos o cancélalos desde la Agenda y vuelve a cargar el cierre.',
    ],

    'notify' => [
        'saved' => ':name guardado.',
        'deleted' => 'Profesional eliminado.',
        'has_bookings' => 'No se puede eliminar: tiene turnos por venir. Desactívalo para que no reciba nuevos y elimínalo cuando los atienda.',
    ],
];
