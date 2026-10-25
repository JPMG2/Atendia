<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Asistente IA del panel de administración
|--------------------------------------------------------------------------
|
| Voz operativa y seca. Lo que el modelo dice sale de sus instrucciones; esto
| es lo que imprime el panel. Las frases con imperativo tienen su voseo en
| lang/es_AR/ask_admin.php.
|
*/

return [
    'button' => 'Asistente IA',
    'title' => 'Asistente IA',
    'subtitle' => 'Consulta la plataforma, sus negocios y sus pantallas',
    'hello' => 'Hola, :name',
    'intro' => 'Soy el asistente IA de :brand. Conozco los negocios, los cobros, los avisos y cada pantalla de los dos paneles. Solo consulto: no cambio nada ni toco los mensajes de los clientes de cada negocio.',
    'placeholder' => 'Pregunta por un negocio',
    'field' => 'pregunta',
    'send' => 'Enviar',
    'thinking' => 'Consultando…',
    'failed' => 'No pude responder ahora. La pregunta no se cobró del tope diario; vuelve a intentarlo en un momento.',
    'suggestions_title' => 'Para empezar',
    'new_chat' => 'Nueva conversación',
    'left' => '{0}Llegaste al tope de preguntas de hoy. Vuelve mañana o súbelo en Ajustes.|{1}Queda 1 pregunta de hoy|[2,*]Quedan :count preguntas de hoy',
    'used_up' => 'Llegaste al tope de :cap preguntas de hoy. Se renueva mañana; el tope se cambia en Ajustes.',

    'ask_button' => 'Preguntar',

    'today' => [
        'title' => 'Lo que necesita tu atención hoy',
        'clear' => 'Todo al día: no hay nada pendiente por ahora.',
        'receipts' => '{1} 1 comprobante espera verificación|[2,*] :count comprobantes esperan verificación',
        'alerts' => '{1} 1 aviso crítico abierto|[2,*] :count avisos críticos abiertos',
        'mentions' => '{1} 1 mención por verificar|[2,*] :count menciones por verificar',
        'support' => '{1} 1 reporte espera respuesta|[2,*] :count reportes esperan respuesta',
        'line' => 'La línea de WhatsApp de la plataforma no está conectada',
    ],

    // The question each "Preguntar" button says: every one is answerable by a tool the assistant already has.
    'ask' => [
        'pulse' => '¿Cómo está la plataforma hoy?',
        'queue' => '¿Qué comprobantes esperan verificación y cuál lleva más tiempo?',
        'leaving' => '¿Qué negocios se están yendo?',
        'struggling' => '¿Qué negocios les cuesta pagar?',
        'alert' => '¿Qué hago con el aviso «:kind» de :business?',
    ],

    'suggestions' => [
        'pulse' => '¿Cómo está la plataforma hoy?',
        'alerts' => '¿Qué avisos tengo abiertos?',
        'payments' => '¿Qué comprobantes esperan verificación?',
        'trouble' => '¿Qué negocios les cuesta pagar?',
    ],

    'skills' => [
        'admin' => 'Panel de administración',
        'client' => 'Panel del cliente (lo que ve cada negocio)',
        'description' => 'Cómo funciona «:screen» en el :where de :brand: qué muestra, qué significa cada dato y cómo se usa',
    ],
];
