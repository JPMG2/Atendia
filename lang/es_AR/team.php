<?php

declare(strict_types=1);

// Solo overrides de voseo; lo que no está cae a lang/es/team.php.
return [
    'sub' => 'Quién atiende junto a tu asistente. Invitá a tu equipo y ordenalo por departamentos.',

    'people' => [
        'you' => 'Vos',
    ],

    'departments' => [
        'when' => 'Deriva acá cuando',
        'nobody' => 'Sin personas: el aviso te llega a vos',
    ],

    'join' => [
        'title' => 'Sumate al equipo de :business',
        'sub' => 'Creá tu contraseña para entrar con :email.',
        'password_confirmation' => 'Repetí la contraseña',
        'welcome' => 'Ya sos parte del equipo de :business.',
        'expired_body' => 'Venció o ya se usó. Pedile a quien te invitó que te la reenvíe desde Equipo.',
        'taken' => 'Ese correo ya tiene una cuenta en AtendIa. Entrá con él o pedí que te inviten con otro.',
    ],

    'department' => [
        'when_hint' => 'Tu asistente lo lee para elegir a quién derivar. Escribilo como se lo dirías a una persona nueva.',
    ],

    'availability' => [
        'hint_available' => 'Recibís los avisos de tu departamento.',
    ],

    'how' => [
        'invite' => 'Invitás a tu equipo por correo y cada persona crea su contraseña.',
    ],

    'invite' => [
        'departments_hint' => 'Podés cambiarlos cuando quieras.',
    ],
];
