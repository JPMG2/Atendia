<?php

declare(strict_types=1);

// "Pregúntale a AtendIa": el asistente del panel cliente que responde a la
// dueña con los datos de su negocio. Solo consultas; el cupo va por plan.
return [
    'button' => 'Pregúntale a AtendIa',
    'title' => 'Pregúntale a AtendIa',
    'subtitle' => 'Te respondo con los datos de tu negocio.',
    'hello' => 'Hola, :name',
    'assistant' => 'asistente :ia de Atendia',
    'ia' => 'IA',
    'intro' => 'Soy el :assistant: te respondo con los datos de tu negocio y te explico cómo usar cada módulo del panel. ¿Qué quieres saber?',
    'suggestions_title' => 'Puedes preguntar, por ejemplo:',
    'field' => 'pregunta',
    'placeholder' => 'Escribe tu pregunta…',
    'send' => 'Preguntar',
    'thinking' => 'Buscando en tus datos…',
    'failed' => 'No pude responder ahora. Intenta de nuevo en un momento.',
    'used_up' => 'Usaste tus :cap consultas de este mes. Se renuevan el :date.',

    'quota' => [
        'left' => 'Te quedan',
        'of' => 'de :cap consultas este mes',
        'used' => '{0} Sin usar todavía|{1} 1 usada|[2,*] :count usadas',
        'renews' => 'Se renuevan el :date.',
        'of_short' => 'de :cap',
    ],

    'today' => [
        'title' => 'Lo que necesita tu atención hoy',
        'clear' => 'Todo al día: no hay nada pendiente por ahora.',
        'waiting' => '{1} 1 conversación espera a tu equipo|[2,*] :count conversaciones esperan a tu equipo',
        'to_teach' => '{1} 1 respuesta para enseñarle a tu asistente|[2,*] :count respuestas para enseñarle a tu asistente',
        'birthdays' => '{1} 1 cliente cumple años hoy|[2,*] :count clientes cumplen años hoy',
        'conversations' => '{1} 1 conversación con clientes hoy|[2,*] :count conversaciones con clientes hoy',
    ],

    'rate' => [
        'up' => 'Me sirvió',
        'down' => 'No me sirvió',
        'thanks' => 'Gracias, lo tenemos en cuenta.',
    ],

    // El botón de cada gráfico de "Mis estadísticas": abre el panel con la pregunta armada.
    'chart' => [
        'button' => 'Preguntar',
        'topics' => 'Explícame el bloque de temas de Mis estadísticas con mis números: ¿qué preguntan más mis clientes y qué me conviene hacer?',
        'daily' => 'Explícame el gráfico de conversaciones por día de Mis estadísticas con mis números.',
        'hours' => 'Explícame el gráfico de horas pico de Mis estadísticas con mis números.',
        'trend' => 'Explícame la tendencia por mes de Mis estadísticas con mis números.',
        'gaps' => 'Explícame qué piden mis clientes que no está en mi catálogo.',
    ],

    'locked' => [
        'title' => 'Pregúntale a AtendIa por tu negocio',
        'body' => 'Pregunta con tus palabras y te respondo con tus datos reales: consultas, clientes, cumpleaños y más.',
        'plan' => 'Viene con el plan :plan: :cap consultas al mes.',
        'examples' => 'Preguntas que podrías hacer:',
        'cta' => 'Ver planes',
    ],

    // Lo que la pantalla no dice pero la IA tiene que saber explicar: cómo
    // se mide cada número. Espejo de App\Classes\Main\Statistics.
    'guide' => [
        'statistics' => [
            'conversations' => 'Conversaciones: las conversaciones donde un cliente escribió al menos un mensaje en el mes.',
            'new_contacts' => 'Contactos nuevos: conversaciones que empezaron por primera vez en el mes.',
            'questions' => 'Preguntas: las preguntas de clientes que Atendia detectó al leer las conversaciones del mes.',
            'resolution' => 'Resueltas por el asistente: de esas preguntas, el porcentaje que respondió el asistente solo, sin el equipo. Se mide por pregunta, no por conversación: si respondió 3 de 4 preguntas de una charla, cuentan las 3.',
            'audio_minutes' => 'Minutos de audio: los minutos de notas de voz de clientes que el asistente transcribió en el mes.',
            'recovered' => 'Clientes recuperados: clientes a los que se les avisó una respuesta que el negocio enseñó después y que volvieron a escribir.',
            'daily' => 'Gráfico diario: conversaciones con mensajes de clientes por día, últimos 30 días; los días sin actividad cuentan cero.',
            'hours' => 'Horas pico: la franja de 4 horas seguidas con más mensajes recibidos en el mes.',
            'topics' => 'Temas: las preguntas del mes agrupadas por tema, con cuántas veces se preguntó, el porcentaje que resolvió solo el asistente y qué hacer con el resto (enseñar una respuesta o sumar algo al catálogo).',
            'trend' => 'Tendencia: conversaciones por mes de los últimos 6 meses.',
            'gaps' => 'Lo que piden y no está en el catálogo: lo que los clientes pidieron por nombre este mes y no está en el catálogo.',
            'plans' => 'Profundidad por plan: Emprende ve los números del mes; Negocio suma temas y gráfico diario; Premium suma horas pico, tendencia y lo que piden y no está en el catálogo.',
            'delta' => 'La flecha de cada número compara con el mes anterior completo.',
        ],
    ],

    'suggestions' => [
        'overnight' => '¿Qué consultaron anoche?',
        'today' => '¿Qué consultaron hoy?',
        'unresolved' => '¿Qué conversaciones quedaron sin resolver?',
        'birthdays' => '¿Quién cumple años esta semana?',
        'last_week' => '¿Cómo nos fue la semana pasada?',
        'top_topic' => '¿Qué es lo que más preguntan este mes?',
    ],
];
