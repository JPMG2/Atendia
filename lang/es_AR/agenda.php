<?php

declare(strict_types=1);

// Only the voseo overrides: everything else falls back to lang/es.
return [

    'reminder' => '🔔 *:business*: te esperamos :day a las :time para :what. Respondé *Confirmo* si venís, o *Reprogramar* si necesitás otro horario.',

    'public' => [
        'sub' => 'Elegí el día y la hora que te queden bien. Te confirmamos al instante.',
        'pick_hour_hint' => 'Tocá la que prefieras.',
        'name_placeholder' => 'Como querés que te llamemos',
        'taken' => 'Esa hora se acaba de ocupar. Elegí otra, por favor.',
        'done_body' => ':business te espera el :when. Si necesitás cambiarlo, escribile por WhatsApp.',
        'link_hint' => 'Pegalo en tu Instagram o en tu perfil de WhatsApp: tus clientes eligen hueco sin escribirte.',
    ],

    'empty' => [
        'body' => 'Cuando alguien reserve por WhatsApp, aparece acá. También lo podés anotar vos.',
    ],

    'free' => [
        'sub' => 'Las que tu asistente ofrece hoy mismo. Tocá una para anotar un turno.',
    ],

    'form' => [
        'move_sub' => 'Elegí el nuevo día y la nueva hora.',
        'customer_placeholder' => 'Buscá por nombre o teléfono',
        'time_placeholder' => 'Elegí una hora libre',
    ],

];
