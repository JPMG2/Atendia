<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Landing — variante rioplatense (voseo) · Argentina / Uruguay
|--------------------------------------------------------------------------
|
| SOLO las claves que cambian respecto del neutro. Todo lo que no esté acá
| cae automáticamente a lang/es/landing.php por fallback_locale. No copiar
| el archivo entero: mantener únicamente lo que de verdad difiere.
|
*/

return [

    'hero' => [
        'badge' => 'Atiende por vos en WhatsApp',
        'subtitle' => 'Conectá tu WhatsApp y dejá que el asistente responda, agende turnos y muestre tus productos. Vos lo configurás en minutos desde un panel simple — sin saber de tecnología.',
    ],

    'features' => [
        'subtitle' => 'Sea una clínica o un kiosco, Atendia se adapta a cómo trabajás.',
        'schedule' => [
            'body' => 'Definí días, horarios y capacidad. El asistente ofrece huecos libres y confirma sin que muevas un dedo.',
        ],
        'catalog' => [
            'body' => 'Cargá productos con precio y foto. Si preguntan, responde con la info y el link de compra al instante.',
        ],
        'always' => [
            'body' => 'Contesta consultas frecuentes en segundos, en tu tono, incluso cuando dormís o estás con un cliente.',
        ],
        'alerts' => [
            'body' => 'Recibís un resumen de turnos y conversaciones. Intervenís solo cuando hace falta.',
        ],
        'control' => [
            'title' => 'Vos tenés el control',
        ],
    ],

    'how' => [
        'step3' => [
            'title' => 'Responde por vos',
        ],
        'step4' => [
            'title' => 'Vos supervisás',
            'body' => 'Revisás todo desde el panel y tomás el control cuando quieras.',
        ],
    ],

    'pricing' => [
        'subtitle' => 'Empezá gratis. Cambiá o cancelá cuando quieras.',
        'starter' => [
            'desc' => 'Probá Atendia con tu propio número.',
        ],
    ],

    'closing' => [
        'subtitle' => 'Conectá tu WhatsApp y que Atendia responda por vos en minutos.',
    ],

    'phone' => [
        'b1' => 'Hola, ¿tenés turno para un electro esta semana?',
    ],

];
