<?php

declare(strict_types=1);

return [

    'title' => 'Agenda',
    'sub' => 'Los turnos del día y las horas que todavía están libres.',
    'book' => 'Reservar un turno',
    'today' => 'Hoy',
    'previous' => 'Día anterior',
    'next' => 'Día siguiente',
    'pick_day' => 'Ir a un día',
    'view_day' => 'Día',
    'view_week' => 'Semana',
    'week_of' => 'Semana del :day',
    'week_none' => 'Sin turnos',
    'week_closed' => 'Cerrado',
    'week_free' => '{0} sin horas libres|{1} 1 hora libre|[2,*] :count horas libres',
    'no_name' => 'Sin nombre',
    'plain_slot' => 'Turno general',
    'move' => 'Cambiar de horario',
    'cancel' => 'Cancelar el turno',
    'mark_done' => 'Marcar como atendido',
    'cancel_title' => '¿Cancelar este turno?',
    'cancel_message' => 'La hora de las :when vuelve a quedar libre y tu asistente la puede ofrecer.',
    'cancel_accept' => 'Cancelar el turno',

    'status' => [
        'confirmed' => 'Confirmado',
        'cancelled' => 'Cancelado',
        'done' => 'Atendido',
        'no_show' => 'No vino',
    ],

    'off' => [
        'title' => 'Los turnos están apagados',
        'body' => 'Por eso no hay horas para ofrecer: no es que el día esté lleno. Enciéndelos en Mi negocio · Turnos y tu asistente empieza a dar hora por WhatsApp.',
        'cta' => 'Activar los turnos',
    ],

    'empty' => [
        'title' => 'No hay turnos este día',
        'body' => 'Cuando alguien reserve por WhatsApp, aparece acá. También puedes anotarlo tú.',
    ],

    'free' => [
        'title' => 'Horas libres',
        'sub' => 'Las que tu asistente ofrece ese día. Toca una para anotar un turno.',
        'none' => 'No queda ninguna hora libre este día.',
    ],

    'form' => [
        'title' => 'Reservar un turno',
        'sub' => 'Para alguien que ya está en tus clientes.',
        'move_title' => 'Cambiar de horario',
        'move_sub' => 'Elige el nuevo día y la nueva hora.',
        'customer' => 'Cliente',
        'customer_placeholder' => 'Busca por nombre o teléfono',
        'customer_empty' => 'No hay ningún cliente con ese nombre.',
        'service' => 'Servicio',
        'service_placeholder' => 'Sin servicio: turno general',
        'day' => 'Día',
        'time' => 'Hora',
        'time_placeholder' => 'Elige una hora libre',
        'notes' => 'Nota',
        'notes_hint' => 'Lo que pidió, en una línea. Tu asistente no la lee.',
        'cancel' => 'Cancelar',
        'save' => 'Reservar',
        'move_save' => 'Guardar el cambio',
    ],

    'public' => [
        'title' => 'Reserva tu turno',
        'sub' => 'Elige el día y la hora que te queden bien. Te confirmamos al instante.',
        'pick_hour' => 'Horas libres',
        'pick_hour_hint' => 'Toca la que prefieras.',
        'name' => 'Tu nombre',
        'name_placeholder' => 'Como quieres que te llamemos',
        'phone' => 'Tu WhatsApp',
        'phone_hint' => 'Con código de país, sin espacios. Ahí te escribimos si algo cambia.',
        'reminder_hint' => '¿Quieres que te recordemos el turno? Envíanos el mensaje que te dejamos listo y te escribimos antes de la hora.',
        'reminder_button' => 'Recibir mi recordatorio por WhatsApp',
        'reminder_text' => 'Hola, reservé en :business para el :when. Quiero recibir el recordatorio.',
        'submit' => 'Reservar mi turno',
        'taken' => 'Esa hora se acaba de ocupar. Elige otra, por favor.',
        'too_many' => 'Ya se hicieron varias reservas seguidas desde aquí. Si necesitas otra, escríbele al negocio por WhatsApp.',
        'too_many_phone' => 'Este número ya tiene varias reservas hoy en este negocio. Si necesitas otra, escríbele por WhatsApp.',
        'service_gone' => 'Ese servicio ya no se reserva por aquí. Elige otro o reserva solo la hora.',
        'done_title' => '¡Listo, quedó reservado!',
        'done_body' => ':business te espera el :when. Si necesitas cambiarlo, escríbele por WhatsApp.',
        'link_label' => 'Tu link de reservas',
        'link_hint' => 'Pégalo en tu Instagram o en tu perfil de WhatsApp: tus clientes eligen hueco sin escribirte.',
        'link_copy' => 'Copiar',
        'link_copied' => 'Copiado',
        'link_share' => 'Compartir',
        'powered' => 'Reservas atendidas por :brand',
    ],

    // Several wordings on purpose: identical text sent to many people is what spam filters learn first.
    'reminder' => [
        '🔔 *:business*: te esperamos :day a las :time para :what. Responde *Confirmo* si vienes, o *Reprogramar* si necesitas otro horario.',
        '👋 Te recordamos tu cita en *:business*: :day a las :time, :what. Si vienes, responde *Confirmo*; si necesitas cambiarla, escribe *Reprogramar*.',
        '📅 *:business* te espera :day a las :time para :what. Cuéntanos si vienes con *Confirmo* o pide otro horario con *Reprogramar*.',
    ],

    // Se cierra el día y alguien tenía la hora tomada: enterarse al llegar a
    // la puerta cerrada es lo único que no se puede permitir.
    // Varias redacciones: el mismo texto a muchos es lo primero que aprende un filtro de spam.
    'closure_notice' => [
        '*:business*: tenemos que cancelar tu turno del :when porque ese día no abrimos. Escríbenos y lo reprogramamos.',
        'Hola, te escribimos de *:business*: ese día no vamos a abrir, así que tu turno del :when queda cancelado. Respóndenos y buscamos otro horario.',
        '*:business* te avisa: el :when no abrimos y tuvimos que cancelar tu turno. Escríbenos cuando quieras y lo reprogramamos.',
    ],
    'closure_notice_reason' => [
        '*:business*: tenemos que cancelar tu turno del :when porque ese día no abrimos (:reason). Escríbenos y lo reprogramamos.',
        'Hola, te escribimos de *:business*: ese día no vamos a abrir (:reason), así que tu turno del :when queda cancelado. Respóndenos y buscamos otro horario.',
        '*:business* te avisa: el :when no abrimos (:reason) y tuvimos que cancelar tu turno. Escríbenos cuando quieras y lo reprogramamos.',
    ],

    'notify' => [
        'booked' => 'Turno reservado.',
        'moved' => 'El turno quedó en su nuevo horario.',
        'cancelled' => 'Turno cancelado: la hora volvió a quedar libre.',
        'marked' => 'Turno marcado como atendido.',
        'slot_taken' => 'No pudimos guardar el turno: esa hora ya no está libre.',
    ],

];
