<?php

declare(strict_types=1);

return [
    'title' => 'Equipo',
    'sub' => 'Quién atiende junto a tu asistente. Invita a tu equipo y ordénalo por departamentos.',

    'people' => [
        'title' => 'Personas',
        'sub' => 'Cada persona entra con su correo y su contraseña. Los agentes solo ven las conversaciones de su departamento.',
        'invite' => 'Invitar a alguien',
        'you' => 'Tú',
        'pending' => 'Invitación pendiente',
        'sent' => 'Enviada :when',
        'resend' => 'Reenviar invitación',
        'cancel' => 'Cancelar invitación',
        'edit' => 'Editar',
        'remove' => 'Quitar del equipo',
        'actions' => 'Acciones de :name',
        'save' => 'Guardar cambios',
        'no_whatsapp' => 'Sin WhatsApp: ve los avisos solo en el panel',
        'all_departments' => 'Ve todas las conversaciones',
        'available' => 'Disponible',
        'away' => 'Ausente',
        'remove_title' => '¿Quitar a :name del equipo?',
        'remove_message' => 'Deja de entrar al panel. Sus :count charlas abiertas vuelven a su departamento y todo lo que respondió queda en el historial.',
        'remove_message_none' => 'Deja de entrar al panel. Todo lo que respondió queda en el historial.',
        'remove_accept' => 'Quitar del equipo',
    ],

    'availability' => [
        'label' => 'Tu estado',
        'available' => 'Disponible',
        'away' => 'Ausente',
        'hint_available' => 'Recibes los avisos de tu departamento.',
        'hint_away' => 'Los avisos van a quien esté disponible; si no hay nadie, a la persona titular.',
    ],

    'hours' => [
        'business' => 'Mismo horario del negocio',
        'own' => 'Atiende :hours',
        'label' => 'Horario del departamento',
        'use_business' => 'Usar el horario del negocio',
        'switch_on' => 'El mismo que el negocio',
        'switch_off' => 'Horario propio',
        'hint' => 'Fuera de este horario, tu asistente avisa cuándo le van a responder.',
    ],

    'roles' => [
        'owner' => 'Titular',
        'agent' => 'Agente',
    ],

    'departments' => [
        'title' => 'Departamentos',
        'sub' => 'Tu asistente deriva cada charla al departamento que corresponde, y solo su gente recibe el aviso.',
        'new' => 'Nuevo departamento',
        'empty' => 'Todavía no hay departamentos: hoy cada derivación le llega a todo el equipo.',
        'when' => 'Deriva aquí cuando',
        'waiting' => '1 charla esperando|:count charlas esperando',
        'none_waiting' => 'Nada esperando',
        'nobody' => 'Sin personas: el aviso te llega a ti',
        'edit' => 'Editar :name',
    ],

    'notify' => [
        'invited' => 'Invitación enviada a :email.',
        'resent' => 'Invitación reenviada.',
        'cancelled' => 'Invitación cancelada.',
        'member_saved' => 'Cambios guardados.',
        'removed' => 'Quedó fuera del equipo.',
        'department_saved' => 'Departamento :name guardado.',
        'department_deleted' => 'Departamento eliminado.',
        'plan_needed' => 'Los departamentos vienen con el plan Negocio.',
        'seats_full' => 'Tu plan :plan permite :cap personas en el panel, tu cuenta incluida, y ya están todas. Quita a alguien, cancela una invitación pendiente o cambia de plan.',
    ],

    'join' => [
        'title' => 'Únete al equipo de :business',
        'sub' => 'Crea tu contraseña para entrar con :email.',
        'name' => 'Tu nombre',
        'password' => 'Contraseña',
        'password_confirmation' => 'Repite la contraseña',
        'submit' => 'Entrar al equipo',
        'welcome' => 'Ya eres parte del equipo de :business.',
        'go_login' => 'Ir a iniciar sesión',
        'expired_title' => 'Esta invitación ya no es válida',
        'expired_body' => 'Venció o ya se usó. Pídele a quien te invitó que te la reenvíe desde Equipo.',
        'taken' => 'Ese correo ya tiene una cuenta en AtendIa. Entra con él o pide que te inviten con otro.',
        'seats_full' => 'El equipo de :business ya tiene todos los lugares de su plan ocupados. Pídele que libere uno y vuelve a abrir este enlace.',
    ],

    'department' => [
        'opens' => 'abre',
        'closes' => 'cierra',
        'delete' => 'Eliminar departamento',
        'delete_title' => '¿Eliminar :name?',
        'delete_message' => 'Sus charlas vuelven a todo el equipo y tu asistente deja de derivar aquí.',
        'name' => 'Nombre',
        'when_hint' => 'Tu asistente lo lee para elegir a quién derivar. Escríbelo como se lo dirías a una persona nueva.',
        'people' => 'Quién atiende',
        'closed' => 'Cerrado',
        'save' => 'Guardar departamento',
    ],

    'locked' => [
        'title' => 'Departamentos, con el plan :plan',
        'body' => 'Cada charla llega directo al departamento que corresponde —ventas, pagos, turnos— y solo avisa a su gente.',
        'cta' => 'Ver planes',
    ],

    'how' => [
        'title' => 'Cómo funciona',
        'invite' => 'Invitas a tu equipo por correo y cada persona crea su contraseña.',
        'route' => 'Cuando una charla necesita a una persona, tu asistente elige el departamento según el motivo.',
        'notify' => 'Su gente recibe el aviso por WhatsApp y la charla en su panel.',
    ],

    'invite' => [
        'title' => 'Invitar a tu equipo',
        'sub' => 'Le llega un correo para crear su contraseña.',
        'name' => 'Nombre',
        'email' => 'Correo',
        'whatsapp' => 'WhatsApp para los avisos',
        'whatsapp_hint' => 'Opcional. Sin número, ve los avisos solo en el panel.',
        'departments' => 'Departamentos',
        'departments_hint' => 'Puedes cambiarlos cuando quieras.',
        'send' => 'Enviar invitación',
        'cancel' => 'Cancelar',
    ],
];
