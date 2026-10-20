<?php

declare(strict_types=1);

// Sin imperativo en segunda persona a propósito: así no necesita override de voseo en es_AR.
return [
    'title' => 'Notificaciones',
    'subtitle' => 'Lo que pasó mientras no mirabas el panel',

    'mark_all' => 'Marcar todas como leídas',
    'mark_kind' => 'Marcar estas como leídas',

    // El resumen de arriba: qué hay sin leer, antes de la lista de lo mismo.
    'digest' => [
        'lead' => 'Sin leer:',
        'customer_waiting' => '{1}1 cliente esperando|[2,*]:count clientes esperando',
        'handed_to_team' => '{1}1 charla derivada|[2,*]:count charlas derivadas',
        'appointment_booked' => '{1}1 turno nuevo|[2,*]:count turnos nuevos',
        'whatsapp_disconnected' => '{1}1 corte de WhatsApp|[2,*]:count cortes de WhatsApp',
        'taught_by_teammate' => '{1}1 respuesta enseñada por tu equipo|[2,*]:count respuestas enseñadas por tu equipo',
        'support_resolved' => '{1}1 reporte resuelto|[2,*]:count reportes resueltos',
        'mention_offered' => '{1}1 invitación a ser mencionado|[2,*]:count invitaciones a ser mencionado',
        'mention_needs_follow' => '{1}1 aviso por seguir nuestra cuenta|[2,*]:count avisos por seguir nuestra cuenta',
        'mention_published' => '{1}1 mención publicada|[2,*]:count menciones publicadas',
    ],
    'unread' => ':count sin leer',

    'groups' => [
        'today' => 'Hoy',
        'yesterday' => 'Ayer',
        'earlier' => 'Antes',
    ],

    'empty' => [
        'title' => 'No hay nada pendiente',
        'body' => 'Acá aparece lo que necesita tu atención: un cliente esperando respuesta, un turno nuevo o tu WhatsApp caído.',
    ],

    'settings' => [
        'link' => 'Elegir qué avisos ver',
        'title' => 'Avisos de la campana',
        'sub' => 'Elige qué quieres ver en tu campana. Solo cambia lo tuyo: el resto del equipo sigue viendo lo que eligió.',
        'kinds' => [
            'customer_waiting' => 'Un cliente espera respuesta',
            'handed_to_team' => 'Tu asistente derivó una charla al equipo',
            'appointment_booked' => 'Se reservó un turno nuevo',
            'whatsapp_disconnected' => 'Tu WhatsApp se desconectó',
            'taught_by_teammate' => 'Alguien de tu equipo le enseñó una respuesta',
            'support_resolved' => 'Resolvimos un reporte que enviaste',
            'mention_offered' => 'Te invitamos a que mencionemos tu negocio',
            'mention_needs_follow' => 'Falta que sigas nuestra cuenta para mencionarte',
            'mention_published' => 'Mencionamos tu negocio en nuestras redes',
        ],
    ],

    // Desde 3 iguales la lista deja de repetir la misma frase y muestra la cuenta.
    'bundles' => [
        'customer_waiting' => ':count clientes esperan respuesta',
        'handed_to_team' => 'Tu asistente derivó :count charlas al equipo',
        'appointment_booked' => ':count turnos nuevos',
        'whatsapp_disconnected' => 'Tu WhatsApp se cortó :count veces',
        'taught_by_teammate' => 'Tu equipo le enseñó :count respuestas',
        'support_resolved' => 'Resolvimos :count reportes tuyos',
        'mention_offered' => ':count invitaciones a ser mencionado',
        'mention_needs_follow' => ':count avisos por seguir nuestra cuenta',
        'mention_published' => ':count menciones publicadas',
    ],

    'lines' => [
        'customer_waiting' => ':name espera respuesta desde hace :minutes minutos',
        'handed_to_team' => 'Tu asistente derivó la charla con :name al equipo',
        'appointment_booked' => 'Turno nuevo de :name: :when, :service',
        // Reservado sin elegir servicio (el link público lo permite): la línea
        // que nombra uno terminaría en una coma y nada.
        'appointment_booked_plain' => 'Turno nuevo de :name: :when',
        'whatsapp_disconnected' => 'Tu WhatsApp se desconectó a las :at y dejó de responder',
        'taught_by_teammate' => ':who le enseñó a tu asistente a responder: :question',
        'support_resolved' => 'Resolvimos tu reporte :code',
        'mention_offered' => 'Alcanzaste :achievement: ¿quieres que mencionemos tu negocio en nuestras redes?',
        'mention_needs_follow' => 'Para mencionar tu negocio necesitamos que sigas nuestras cuentas de :accounts',
        'mention_published' => 'Mencionamos tu negocio en nuestras redes por haber alcanzado :achievement',
    ],
];
