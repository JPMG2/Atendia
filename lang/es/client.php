<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Panel del cliente — Inicio
|--------------------------------------------------------------------------
|
| Base neutra (tuteo). Los verbos que cambian con el voseo se sobrescriben
| en lang/es_AR/client.php; lo que no está allá cae a este archivo.
*/

return [
    'home' => [
        'title' => 'Inicio',
        'greeting' => 'Hola, :name',
        'sub_new' => 'Tu asistente está casi listo. Esto es lo que falta.',
        'sub_active' => 'Así viene tu negocio hoy.',
        'mock_label' => 'Vista de maqueta',
        'state_new' => 'Recién llegado',
        'state_active' => 'Activo',
    ],

    'setup' => [
        'title' => 'Tu asistente está listo al :percent%',
        'sub' => 'Completa estos pasos y tu negocio responde solo, a toda hora.',
        'progress' => ':done de :total',
        'steps' => [
            'account' => [
                'label' => 'Creaste tu cuenta',
                'hint' => 'Este ya está. Buen comienzo.',
            ],
            'business' => [
                'label' => 'Completa los datos de tu negocio',
                'hint' => 'Logo, dirección y horarios: tu asistente se presenta mejor.',
                'cta' => 'Completar',
            ],
            'catalog' => [
                'label' => 'Carga tus servicios o productos',
                'hint' => 'Es lo que tu asistente sabe responder.',
                'cta' => 'Cargar',
            ],
            'try' => [
                'label' => 'Prueba tu asistente',
                'hint' => 'Escríbele como si fueras un cliente y míralo responder.',
                'cta' => 'Probar',
            ],
            'whatsapp' => [
                'label' => 'Conecta tu WhatsApp',
                'hint' => 'Desde ese momento responde por ti las 24 horas.',
                'cta' => 'Conectar',
            ],
        ],
    ],

    'previews' => [
        'conversations_title' => 'Conversaciones',
        'conversations_text' => 'Cada charla que tu asistente atienda queda aquí, con lo que respondió.',
        'metrics_title' => 'Tu día en números',
        'metrics_text' => 'Cuántos clientes escribieron y qué preguntaron, en vivo.',
    ],

    'kpis' => [
        'conversations' => 'Conversaciones hoy',
        'handled' => 'Resueltas por la IA',
        'handoffs' => 'Derivadas a ti',
        'response' => 'Respuesta promedio',
    ],

    'recent' => [
        'title' => 'Últimas conversaciones',
        'view_all' => 'Ver todas',
        'samples' => [
            ['name' => 'Mariana', 'text' => '¿Tienen turno para mañana a la tarde?', 'time' => '10:24'],
            ['name' => 'Jorge', 'text' => '¿Cuánto sale el ecodoppler?', 'time' => '09:51'],
            ['name' => 'Valeria', 'text' => '¿Hacen envíos a Palermo?', 'time' => '09:12'],
        ],
    ],
];
