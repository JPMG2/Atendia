<?php

declare(strict_types=1);

return [

    'open' => 'Soporte técnico',
    'title' => 'Soporte técnico',
    'sub' => 'Cuéntanos qué pasó. Ya sabemos en qué pantalla estás.',

    'fields' => [
        'body' => '¿Qué pasó?',
        'kind' => 'Tipo',
        'screen' => 'Pantalla',
        'attachment' => 'Imagen · opcional',
    ],

    'body_hint' => 'Escríbelo como se lo contarías a una persona. Con eso alcanza.',
    'kind_legend' => '¿Qué tipo de mensaje es?',
    'screen_placeholder' => 'No es de una pantalla en particular',
    'attachment_hint' => 'Si una imagen lo explica mejor, súbela tú. Nosotros no tomamos fotos de tu pantalla.',

    'kinds' => [
        'problem' => 'Algo no funciona',
        'idea' => 'Una idea',
        'question' => 'Una pregunta',
    ],

    'statuses' => [
        'new' => 'Nuevo',
        'open' => 'En curso',
        'waiting' => 'Esperando tu respuesta',
        'resolved' => 'Resuelto',
        'closed' => 'Cerrado',
    ],

    'send' => 'Enviar',
    'cancel' => 'Cancelar',

    'sent' => 'Listo. Tu reporte es el :code.',
    'failed' => 'No pudimos enviar tu reporte. Intenta de nuevo en un momento.',
    'no_business' => 'Primero completa el alta de tu negocio.',
    'no_screen' => 'Sin pantalla en particular',

    'thanks_title' => 'Lo recibimos',
    'thanks_body' => 'Tu reporte es el :code. Te escribimos por aquí mismo cuando lo revisemos.',
    'thanks_close' => 'Cerrar',

    'mail' => [
        'subject' => 'Soporte :code · :business',
        'preheader' => 'Reporte nuevo :code',
        'eyebrow' => 'Soporte',
        'title' => 'Reporte :code de :business',
        'cta' => 'Ver en el panel',
        'where' => 'Abierto desde: :screen',
    ],

    'whatsapp' => "Soporte :code · :business\n:kind — :screen\n\n:body",
    'whatsapp_answer' => "Soporte :code — te respondemos:\n\n:reply",

    'admin' => [
        'title' => 'Soporte',
        'sub' => 'Lo que reportan los negocios, por orden de llegada.',
        'empty_title' => 'No hay reportes',
        'empty_body' => 'Cuando un negocio reporte algo desde su panel, aparece aquí.',
        'filter_all' => 'Todos',
        'filter_open' => 'Sin resolver',
        'business' => 'Negocio',
        'opened_by' => 'Abierto por :name',
        'screen' => 'Pantalla',
        'status' => 'Estado',
        'change_status' => 'Cambiar estado de :code',
        'saved' => 'Estado actualizado.',
        'reply' => 'Tu respuesta',
        'reply_hint' => 'Le llega por WhatsApp, donde ya está.',
        'send_reply' => 'Responder',
        'answered' => 'Respuesta enviada.',
        'answered_at' => 'Respondido :when',
        'pain_title' => 'Las pantallas más reportadas',
        'context' => 'Lo que capturamos',
        'attachment' => 'Ver imagen adjunta',
        'count' => '{0}Sin reportes|{1}1 reporte|[2,*]:count reportes',
    ],

];
