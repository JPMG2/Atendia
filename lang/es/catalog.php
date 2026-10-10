<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Catálogos (maestros del sistema)
|--------------------------------------------------------------------------
|
| Base NEUTRA (tuteo): cubre VE, CO, MX, CL y el resto. `es_AR` solo lleva
| overrides de voseo. Hoy este archivo no necesita ninguno: no hay un solo
| verbo en segunda persona ("Volver", "Cancelar", "Guardar cambios" son
| infinitivos), así que el texto es igual en todas las variantes.
|
| `common` es la chrome del editor, igual en todos los maestros. Lo que cambia
| con el género del maestro (moneda es femenina: "Nueva", "Activa") vive en la
| sección del maestro, no acá.
|
*/

return [

    // Chrome del hub de catálogos (la pantalla que lista los maestros).
    'hub' => [
        'page_title' => 'Catálogos del sistema',
        'title' => 'Configuración general',
        'subtitle' => 'Elige un catálogo de la izquierda y configúralo a la derecha.',
        'rail_label' => 'Catálogos',
        'search_placeholder' => 'Busca un catálogo',
        'search_label' => 'Buscar catálogo',
        'no_matches' => 'Ningún catálogo coincide con la búsqueda.',
        'none' => 'No hay catálogos disponibles.',
        'close' => 'Cerrar catálogo',

        // Estado vacío: es lo primero que ve alguien que entra por primera vez,
        // así que explica QUÉ es un catálogo y PARA QUÉ sirve, en vez de repetir
        // la instrucción del encabezado.
        'empty_title' => 'Elige un catálogo para empezar',
        'empty_body' => 'Los catálogos son las listas base del sistema: países, monedas, condiciones fiscales, estados. Lo que definas aquí es lo que después vas a poder elegir en el resto de :brand.',
    ],

    'common' => [
        'back' => 'Volver',
        'cancel' => 'Cancelar',
        'save' => 'Guardar cambios',
        'delete' => 'Eliminar',
        'editing' => 'Editando',
        // Default del <x-inputsform.switch-field>. Cada maestro pasa su propio
        // par para que concuerde el género ("Activa" en moneda, "Activo" en país).
        'on' => 'Activo',
        'off' => 'Inactivo',
    ],

    'country_holiday' => [
        'search_placeholder' => 'Buscar por país, feriado o fecha',
        'search_label' => 'Buscar feriado',
        'singular' => 'feriado',
        'plural' => 'feriados',
        'create' => 'Crear feriado',
        'new' => 'Nuevo',
        'new_title' => 'Nuevo feriado',
        'edit_title' => 'Editar',
        'empty' => 'No hay feriados que coincidan con la búsqueda.',
        'of' => 'de',

        'columns' => [
            'country' => 'País',
            'name' => 'Feriado',
            'when' => 'Cuándo',
            'kind' => 'Tipo',
            'status' => 'Estado',
        ],

        'kinds' => [
            'fixed' => 'Cada año',
            'easter' => 'Desde Pascua',
            'once' => 'Un solo año',
        ],

        'status' => ['active' => 'Vigente', 'inactive' => 'Apagado'],

        'easter' => [
            'sunday' => 'Domingo de Pascua',
            'before' => ':days días antes de Pascua',
            'after' => ':days días después de Pascua',
        ],

        'months' => [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
            7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ],

        'fields' => [
            'country_id' => 'País',
            'country_placeholder' => 'Seleccionar país',
            'name' => 'Nombre del feriado',
            'name_placeholder' => 'Ej. Día de la Independencia',
            'kind' => 'Cuándo cae',
            'kind_hint' => 'Cada año el mismo día, contado desde Pascua, o solo en un año',
            'month' => 'Mes',
            'day' => 'Día',
            'easter_offset' => 'Días desde Pascua',
            'easter_offset_hint' => 'Negativo = antes: -2 es Viernes Santo, 0 el Domingo de Pascua',
            'on_date' => 'Fecha de ese año',
            'on_date_hint' => 'Para lo que un decreto mueve o un puente que se anuncia: no se repite',
            'is_active' => 'Estado',
        ],

        'errors' => [
            'no_such_day' => 'Ese mes no tiene ese día.',
            'range_too_long' => 'Un puente no puede pasar de :days días.',
        ],

        'copy' => [
            'open' => 'Copiar de otro país',
            'title' => 'Copiar feriados a otro país',
            'sub' => 'Se copian los que se repiten cada año; los de un solo año no.',
            'source' => 'Copiar desde',
            'target' => 'Copiar a',
            'preview' => '{1} Se va a crear 1 feriado. Después ajustas el nombre y la fecha de lo que difiera.|[2,*] Se van a crear :count feriados. Después ajustas el nombre y la fecha de lo que difiera.',
            'accept' => '{1} Copiar 1 feriado|[2,*] Copiar :count feriados',
            'done' => '{1} Listo. Se copió 1 feriado.|[2,*] Listo. Se copiaron :count feriados.',
            'nothing' => 'Ese país ya tiene todos los feriados que se repiten cada año.',
            'same' => 'Elige dos países distintos.',
            'failed' => 'No pudimos copiar los feriados. Intenta de nuevo.',
        ],

        'bridge' => [
            'name' => 'Día puente',
            'create_title' => 'Marcar el :date como feriado puente',
            'create_message' => 'Cuenta como no laborable en :country solo en :year. Los demás años no cambia nada.',
            'create_accept' => 'Marcar el día',
            'off_title' => 'Apagar el feriado puente del :date',
            'off_message' => 'Queda guardado, pero deja de contar en :country. Lo puedes volver a marcar desde aquí.',
            'off_accept' => 'Apagar el feriado',
            'created' => 'Listo. El :date quedó como feriado puente.',
            'switched_on' => 'Listo. El feriado puente del :date volvió a contar.',
            'switched_off' => 'Listo. El feriado puente del :date dejó de contar.',
            'failed' => 'No pudimos marcar ese día. Intenta de nuevo.',
            'range_title_one' => 'Marcar 1 día como feriado puente',
            'range_title_many' => 'Marcar :count días como feriado puente',
            'range_message' => 'Del :from al :to en :country. Los fines de semana y los feriados que ya existen no cambian, y cada día cuenta solo en su año.',
            'range_accept' => 'Marcar los días',
            'range_nothing' => 'Esos días ya eran feriado o fin de semana. No cambió nada.',
            'range_done' => '{1} Listo. 1 día quedó como feriado puente.|[2,*] Listo. :count días quedaron como feriado puente.',
            'notify_title' => '{1} ¿Avisar a 1 negocio de :country?|[2,*] ¿Avisar a :count negocios de :country?',
            'notify_message' => 'Les llega un correo con las fechas, para que su horario no los sorprenda. Si no avisas, el feriado cuenta igual.',
            'notify_accept' => 'Enviar el aviso',
            'notify_skip' => 'Ahora no',
            'notified' => '{0} Ningún negocio para avisar.|{1} Listo. Se avisó a 1 negocio.|[2,*] Listo. Se avisó a :count negocios.',
            'mail' => [
                'eyebrow' => 'Calendario',
                'subject' => '{1} Hay un feriado puente en tu país|[2,*] Hay feriados puente en tu país',
                'preheader' => 'Cambia tu horario de atención en esas fechas.',
                'intro' => 'Hola, un aviso sobre el calendario de :name.',
                'body' => '{1} El :dates es feriado puente en :country: tu horario de atención lo toma como día no laborable.|[2,*] Los días :dates son feriado puente en :country: tu horario de atención los toma como no laborables.',
                'cta' => 'Ir a mi panel',
                'closing' => 'Si abres igual, avisa a tus clientes con tiempo.',
            ],
        ],

        'calendar' => [
            'open' => 'Ver el año',
            'title' => 'Feriados de :year',
            'sub' => 'Cada día marcado es un feriado vigente del país.',
            'previous' => 'Año anterior',
            'next' => 'Año siguiente',
            'holiday' => 'Feriado',
            'bridge' => 'Puente posible: cae martes o jueves',
            'bridge_tag' => 'Puente posible',
            'none' => 'Este país no tiene feriados vigentes en :year.',
            'summary' => '{1} 1 feriado en :year, de los cuales :weekend en fin de semana.|[2,*] :count feriados en :year, de los cuales :weekend en fin de semana.',
            'weekdays' => ['L', 'M', 'X', 'J', 'V', 'S', 'D'],
            'days' => [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'],
        ],
    ],

    'plan' => [
        'preview' => [
            'title' => 'Cómo se ve en la landing',
            'note' => 'Sigue lo que escribes, antes de guardar. Nada cambia hasta que guardas.',
        ],
        'search_placeholder' => 'Buscar por nombre o código',
        'search_label' => 'Buscar plan',
        'singular' => 'plan',
        'plural' => 'planes',
        'edit_title' => 'Editar el plan',
        'empty' => 'No hay planes que coincidan con la búsqueda.',
        'impact_one' => 'Hay 1 negocio en este plan: lo que cambies le rige desde que guardas.',
        'impact_many' => 'Hay :count negocios en este plan: lo que cambies les rige desde que guardas.',

        'columns' => [
            'plan' => 'Plan',
            'price' => 'Precio / mes',
            'conversations' => 'Conversaciones',
            'seats' => 'Equipo',
            'ask' => 'Consultas IA',
            'businesses' => 'Negocios',
            'tags' => 'Marcas',
        ],

        'tags' => [
            'featured' => 'Más elegido',
            'trial' => 'Prueba :days días',
        ],

        'statistics' => [
            'counts' => 'Conteos',
            'patterns' => 'Patrones',
            'trends' => 'Tendencias',
        ],

        'switch' => ['on' => 'Incluido', 'off' => 'No incluido'],

        'fields' => [
            'price' => 'Precio mensual (US$)',
            'price_hint' => 'Lo que paga al mes; el anual son 10 meses',
            'conversations_per_month' => 'Conversaciones al mes',
            'team_seats' => 'Personas del equipo',
            'team_seats_hint' => 'Con el titular',
            'messages_per_hour' => 'Mensajes por hora',
            'messages_per_hour_hint' => 'Ritmo máximo por contacto',
            'audio_minutes_per_month' => 'Minutos de audio al mes',
            'audio_hint' => '0 = sin notas de voz',
            'ask_per_month' => 'Consultas a la IA al mes',
            'ask_hint' => '0 = sin asistente de la dueña',
            'catalog_photos' => 'Fotos del catálogo',
            'photos_per_item' => 'Fotos por producto',
            'statistics' => 'Estadísticas',
            'ai_alert_share' => 'Aviso de gasto de IA (%)',
            'ai_alert_share_hint' => 'Del precio, antes de avisarte',
            'trial_days' => 'Días de prueba gratis',
            'trial_days_hint' => 'Vacío = sin prueba. Un solo plan a la vez',
            'is_featured' => 'Más elegido',
            'reads_media' => 'La IA lee fotos y PDF',
            'departments' => 'Departamentos',
            'daily_digest' => 'Resumen diario',
        ],

        'ladder' => [
            'min' => 'No puede ser menor que en :plan (:value).',
            'max' => 'No puede superar lo de :plan (:value).',
            'flag_below' => ':plan lo incluye: un plan de arriba no puede ofrecer menos.',
            'flag_above' => ':plan no lo incluye: un plan de abajo no puede ofrecer más.',
        ],
    ],

    'currency' => [
        'search_placeholder' => 'Buscar por código o nombre',
        'search_label' => 'Buscar moneda',
        'singular' => 'moneda',
        'plural' => 'monedas',
        'create' => 'Crear moneda',
        'new' => 'Nueva',
        'new_title' => 'Nueva moneda',
        'edit_title' => 'Editar',
        'empty' => 'No hay monedas que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Código',
            'name' => 'Nombre',
            'symbol' => 'Símbolo',
            'decimals' => 'Decimales',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        'fields' => [
            'code' => 'Código ISO',
            'code_hint' => '3 letras (ARS, USD)',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Dólar Estadounidense',
            'symbol' => 'Símbolo',
            'symbol_hint' => 'Cómo se muestra: $, US$, €',
            'decimals' => 'Decimales',
            'status' => 'Estado',
        ],
    ],

    'country' => [
        'search_placeholder' => 'Buscar por código o nombre',
        'search_label' => 'Buscar país',
        'singular' => 'país',
        'plural' => 'países',
        'create' => 'Crear país',
        'new' => 'Nuevo',
        'new_title' => 'Nuevo país',
        'edit_title' => 'Editar',
        'empty' => 'No hay países que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Código',
            'iso2' => 'ISO-2',
            'name' => 'Nombre',
            'phone_code' => 'Cód. telefónico',
            'currency' => 'Moneda',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
        ],

        'fields' => [
            'code' => 'Código ISO',
            'code_hint' => '3 letras (ARG, USA)',
            'iso2' => 'Código ISO-2',
            'iso2_hint' => '2 letras (AR, US)',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. República Dominicana',
            'phone_code' => 'Código telefónico',
            'phone_code_hint' => 'Sin el +: 54, 1809',
            'currency' => 'Moneda',
            // Infinitivo a propósito: "Elegí/Elige" obligaría a un override de
            // voseo en es_AR solo por este placeholder.
            'currency_placeholder' => 'Seleccionar moneda',
            'status' => 'Estado',
        ],
    ],

    'social_network' => [
        'search_placeholder' => 'Buscar por nombre o abreviatura',
        'search_label' => 'Buscar red social',
        'singular' => 'red social',
        'plural' => 'redes sociales',
        'create' => 'Crear red social',
        'new' => 'Nueva',
        'new_title' => 'Nueva red social',
        'edit_title' => 'Editar',
        'empty' => 'No hay redes sociales que coincidan con la búsqueda.',

        'columns' => [
            'name' => 'Nombre',
            'abbreviation' => 'Abrev.',
            'url' => 'URL',
            'icon' => 'Ícono',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        'fields' => [
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Instagram',
            'abbreviation' => 'Abreviatura',
            'abbreviation_hint' => 'Corta: IG, FB, WA',
            'url' => 'URL base',
            'url_hint' => 'Con https://, la página principal de la red',
            'icon' => 'Ícono',
            // Infinitivo a propósito: "Elegí/Elige" obligaría a un override de
            // voseo en es_AR solo por este placeholder.
            'icon_placeholder' => 'Seleccionar ícono',
            'icon_hint' => 'Glifos disponibles en el sistema',
            'status' => 'Estado',
        ],
    ],

    'support_reply' => [
        'search_placeholder' => 'Buscar por nombre o texto',
        'search_label' => 'Buscar respuesta',
        'singular' => 'respuesta',
        'plural' => 'respuestas',
        'create' => 'Crear respuesta',
        'new' => 'Nueva',
        'new_title' => 'Nueva respuesta',
        'edit_title' => 'Editar',
        'empty' => 'No hay respuestas que coincidan con la búsqueda.',

        'columns' => [
            'name' => 'Nombre',
            'body' => 'Texto',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        'fields' => [
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Reconectar el WhatsApp',
            'body' => 'Texto',
            'body_hint' => 'Es lo que se pega en la respuesta; después se puede ajustar antes de enviar.',
            'status' => 'Estado',
        ],
    ],

    'adoption_nudge' => [
        'search_placeholder' => 'Buscar por paso, asunto o texto',
        'search_label' => 'Buscar mensaje',
        'singular' => 'mensaje',
        'plural' => 'mensajes',
        'create' => 'Crear mensaje',
        'new' => 'Nuevo',
        'new_title' => 'Nuevo mensaje',
        'edit_title' => 'Editar',
        'empty' => 'No hay mensajes que coincidan con la búsqueda.',

        'columns' => [
            'step' => 'Paso',
            'subject' => 'Asunto',
            'body' => 'Texto',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
        ],

        'fields' => [
            'step' => 'Paso en el que se quedó',
            'step_placeholder' => 'Elige el paso',
            'subject' => 'Asunto',
            'subject_placeholder' => 'Ej. ¿Te ayudamos a crear tu negocio?',
            'body' => 'Texto',
            'body_hint' => 'Se abre como correo a la persona. Puedes usar {nombre} y {negocio}: se reemplazan por los de la cuenta.',
            'status' => 'Estado',
        ],
    ],

    'seasonal_window' => [
        'search_placeholder' => 'Buscar por nombre',
        'search_label' => 'Buscar temporada',
        'singular' => 'temporada',
        'plural' => 'temporadas',
        'create' => 'Crear temporada',
        'new' => 'Nueva',
        'new_title' => 'Nueva temporada',
        'edit_title' => 'Editar',
        'empty' => 'No hay temporadas que coincidan con la búsqueda.',

        'columns' => [
            'name' => 'Temporada',
            'starts_at' => 'Desde',
            'ends_at' => 'Hasta',
            'priority' => 'Prioridad',
            'running' => 'Hoy',
            'status' => 'Estado',
            'repeat' => 'Repetir',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
            'running' => 'En curso',
            'waiting' => 'Fuera de fecha',
        ],

        'repeat' => 'Repetir el año que viene',
        'repeated' => 'Listo: :name quedó creada y apagada, para que la revises antes.',
        'repeat_exists' => 'Esa temporada ya tiene su copia del año que viene.',
        'toggled_on' => ':name quedó activa.',
        'toggled_off' => ':name quedó inactiva.',

        'fields' => [
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Navidad 2026',
            'range' => 'Desde y hasta',
            'range_hint' => 'La temporada se prende sola el primer día y se apaga sola el último',
            'starts_at' => 'Desde',
            'ends_at' => 'Hasta',
            'priority' => 'Prioridad',
            'priority_hint' => 'Si dos temporadas se pisan, manda la de número más alto',
            'status' => 'Estado',
        ],
    ],

    'demo_tag' => [
        'search_placeholder' => 'Buscar por clave, nombre o temporada',
        'search_label' => 'Buscar ejemplo',
        'singular' => 'ejemplo',
        'plural' => 'ejemplos',
        'create' => 'Crear ejemplo',
        'new' => 'Nuevo',
        'new_title' => 'Nuevo ejemplo',
        'edit_title' => 'Editar',
        'empty' => 'No hay ejemplos que coincidan con la búsqueda.',

        'columns' => [
            'slug' => 'Clave',
            'label' => 'Pastilla',
            'business_name' => 'Negocio',
            'season' => 'Temporada',
            'sort_order' => 'Orden',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'evergreen' => 'Siempre',
            'inherits' => 'Hereda',
        ],

        'fields' => [
            'slug' => 'Clave',
            'slug_hint' => 'La misma del negocio de ejemplo: ferreteria, kiosco',
            'label' => 'Pastilla',
            'label_placeholder' => 'Ej. Ferretería',
            'business_name' => 'Nombre del negocio',
            'business_name_placeholder' => 'Ej. Ferretería El Tornillo',
            'noun' => 'Rubro en minúscula',
            'noun_hint' => 'Se usa en el botón: "Quiero esto para mi ferretería"',
            'season' => 'Temporada',
            'season_placeholder' => 'Siempre (sin temporada)',
            'season_hint' => 'Sin temporada es la versión de todo el año; con temporada, solo esos días',
            'chips' => 'Preguntas sugeridas',
            'chips_hint' => 'Una por línea. En blanco hereda las de la versión de todo el año',
            'pool' => 'Conversación de ejemplo',
            'pool_hint' => 'Una burbuja por línea: «cliente: …» o «asistente: …»',
            'sort_order' => 'Orden',
            'sort_order_hint' => 'De menor a mayor, como aparecen en el hero',
            'status' => 'Estado',
        ],

        'preview' => [
            'title' => 'Ver como si fuera',
            'hint' => 'Elige un día y mira con qué ejemplos abre el hero. No cambia nada.',
            'date' => 'Día',
            'none' => 'Ese día no hay ninguna temporada: sale la versión de todo el año.',
            'season' => 'Temporada en curso: :name',
            'empty' => 'Ese día el hero no tendría ningún ejemplo que mostrar.',
            'no_script' => 'Este ejemplo no tiene conversación cargada: el hero abriría el chat vacío.',
        ],
    ],

    'province' => [
        'search_placeholder' => 'Buscar por nombre o país',
        'search_label' => 'Buscar provincia',
        'singular' => 'provincia',
        'plural' => 'provincias',
        'create' => 'Crear provincia',
        'new' => 'Nueva',
        'new_title' => 'Nueva provincia',
        'edit_title' => 'Editar',
        'empty' => 'No hay provincias que coincidan con la búsqueda.',

        'columns' => [
            'name' => 'Nombre',
            'country' => 'País',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        'fields' => [
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Buenos Aires',
            'country' => 'País',
            'country_placeholder' => 'Seleccionar país',
            'status' => 'Estado',
        ],
    ],

    'region' => [
        'search_placeholder' => 'Buscar por nombre, provincia o país',
        'search_label' => 'Buscar región',
        'singular' => 'región',
        'plural' => 'regiones',
        'create' => 'Crear región',
        'new' => 'Nueva',
        'new_title' => 'Nueva región',
        'edit_title' => 'Editar',
        'empty' => 'No hay regiones que coincidan con la búsqueda.',

        'columns' => [
            'name' => 'Nombre',
            'province' => 'Provincia',
            'country' => 'País',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        'fields' => [
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Zona Norte',
            'province' => 'Provincia',
            'province_placeholder' => 'Seleccionar provincia',
            'status' => 'Estado',
        ],
    ],

    'tax_condition' => [
        'search_placeholder' => 'Buscar por código o nombre',
        'search_label' => 'Buscar condición fiscal',
        'singular' => 'condición fiscal',
        'plural' => 'condiciones fiscales',
        'create' => 'Crear condición',
        'new' => 'Nueva',
        'new_title' => 'Nueva condición fiscal',
        'edit_title' => 'Editar',
        'empty' => 'No hay condiciones fiscales que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Código',
            'name' => 'Nombre',
            'country' => 'País',
            'discriminate_tax' => 'Discrimina',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        // Sí/No para el switch de discriminación: no es un estado de alta/baja,
        // es una característica de la condición fiscal.
        'discriminate' => [
            'yes' => 'Sí',
            'no' => 'No',
        ],

        'fields' => [
            'code' => 'Código',
            'code_hint' => 'Corto: RI, MT, EX',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Responsable Inscripto',
            'country' => 'País',
            'country_placeholder' => 'Seleccionar país',
            'discriminate_tax' => 'Discrimina impuesto',
            'status' => 'Estado',
        ],
    ],

    'status' => [
        'search_placeholder' => 'Buscar por nombre',
        'search_label' => 'Buscar estado',
        'singular' => 'estado',
        'plural' => 'estados',
        'create' => 'Crear estado',
        'new' => 'Nuevo',
        'new_title' => 'Nuevo estado',
        'edit_title' => 'Editar',
        'empty' => 'No hay estados que coincidan con la búsqueda.',

        'columns' => [
            'name' => 'Nombre',
            'color' => 'Color',
        ],

        'fields' => [
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. En proceso',
            'color' => 'Color',
            'color_placeholder' => 'Seleccionar color',
            'color_hint' => 'Con el que se pinta este estado en todo el sistema',
        ],

        // La paleta es semántica: el nombre dice para QUÉ sirve el color, no solo
        // qué tono es. Espeja CurrentStatus::COLORS.
        'colors' => [
            'success' => 'Verde (todo bien)',
            'info' => 'Azul (informativo)',
            'warning' => 'Ámbar (atención)',
            'danger' => 'Rojo (problema)',
            'brand' => 'Jade (marca)',
            'neutral' => 'Gris (sin relevancia)',
        ],
    ],

    // --- Grupo Negocio: lo que el negocio elige al configurarse ---

    'service_type' => [
        'search_placeholder' => 'Buscar por clave, nombre, modalidad o atributo',
        'search_label' => 'Buscar tipo de servicio',
        'singular' => 'tipo de servicio',
        'plural' => 'tipos de servicio',
        'create' => 'Crear tipo',
        'new' => 'Nuevo',
        'new_title' => 'Nuevo tipo de servicio',
        'edit_title' => 'Editar',
        'empty' => 'No hay tipos de servicio que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Clave',
            'name' => 'Tipo de servicio',
            'modality' => 'Modalidad',
            'sector' => 'Rubro',
            'attributes' => 'Atributos',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
        ],

        'attributes' => [
            'title' => 'Atributos del tipo',
            'hint' => 'Los campos que un negocio completa al adoptar este tipo. El orden de las filas es el orden en la ficha.',
            'field' => 'Atributo',
            'placeholder' => 'Elegir atributo…',
            'label' => 'Etiqueta',
            'label_placeholder' => 'Vacía = el nombre del atributo',
            'hint_field' => 'Ayuda',
            'hint_placeholder' => 'Vacía = la descripción del atributo',
            'required' => 'Obligatorio',
            'required_on' => 'Sí',
            'required_off' => 'No',
            'add' => 'Agregar atributo',
            'remove' => 'Quitar este atributo',
        ],

        'suggest' => [
            'hint' => 'Sugiere este tipo a todas las actividades de su rubro. Ninguna queda obligada: el catálogo sugiere, no impone.',
            'button' => 'Sugerir a todo el rubro',
            'done' => '{1}Sugerido a :count actividad nueva del rubro.|[2,*]Sugerido a :count actividades nuevas del rubro.',
            'none' => 'Ya estaba sugerido a todo su rubro.',
            'needs_sector' => 'Falta elegir el rubro del tipo.',
        ],

        'fields' => [
            'code' => 'Clave',
            'code_hint' => 'Sin espacios ni acentos: consulta, pedido-llevar',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Consulta',
            'modality' => 'Modalidad',
            'modality_placeholder' => 'Elige cómo se ofrece',
            'modality_hint' => 'Una sola: decide qué pregunta el asistente',
            'description' => 'Descripción',
            'description_placeholder' => 'Ej. Atención con turno, uno a la vez',
            'description_hint' => 'Ayuda al negocio a saber si es lo que ofrece',
            'sector' => 'Rubro',
            'sector_placeholder' => 'Sin rubro',
            'sector_hint' => 'Solo agrupa esta pantalla; a quién se le ofrece lo deciden las actividades',
            'order' => 'Orden',
            'order_hint' => 'En qué posición se le sugiere al negocio',
            'status' => 'Estado',
        ],
    ],

    'service_modality' => [
        'search_placeholder' => 'Buscar por clave, nombre o descripción',
        'search_label' => 'Buscar modalidad',
        'singular' => 'modalidad',
        'plural' => 'modalidades',
        'create' => 'Crear modalidad',
        'new' => 'Nueva',
        'new_title' => 'Nueva modalidad',
        'edit_title' => 'Editar',
        'empty' => 'No hay modalidades que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Clave',
            'name' => 'Nombre',
            'description' => 'Qué pide y qué recuerda',
            'order' => 'Orden',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        'fields' => [
            'code' => 'Clave',
            'code_hint' => 'Es a lo que se engancha el sistema: cita, reserva, alquiler',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Cita / Turno',
            'description' => 'Descripción',
            'description_placeholder' => 'Ej. Fecha, hora y duración con un profesional',
            'description_hint' => 'Qué le pregunta el asistente y qué recuerda el sistema',
            'icon' => 'Ícono',
            'icon_placeholder' => 'Elige un glifo',
            'icon_hint' => 'Con el que se muestra en el chip de la modalidad',
            'order' => 'Orden',
            'order_hint' => 'En qué posición se le ofrece al negocio',
            'status' => 'Estado',
        ],
    ],

    'service_attribute' => [
        'search_placeholder' => 'Buscar por clave, nombre o tipo',
        'search_label' => 'Buscar atributo',
        'singular' => 'atributo',
        'plural' => 'atributos',
        'create' => 'Crear atributo',
        'new' => 'Nuevo',
        'new_title' => 'Nuevo atributo',
        'edit_title' => 'Editar',
        'empty' => 'No hay atributos que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Clave',
            'name' => 'Nombre',
            'type' => 'Tipo de dato',
            'description' => 'Descripción',
            'options' => 'Opciones',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
        ],

        // Cardinalidad: "Obra social" no es una sola (OSDE, Swiss Medical…).
        'multiple' => [
            'on' => 'Varios',
            'off' => 'Uno',
        ],

        'fields' => [
            'code' => 'Clave',
            'code_hint' => 'Sin espacios ni acentos: obra_social, apto_celiaco',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Duración',
            'data_type' => 'Tipo de dato',
            'data_type_placeholder' => 'Elige el tipo',
            'data_type_hint' => 'Decide con qué campo se carga el valor',
            'description' => 'Descripción',
            'description_placeholder' => 'Ej. Cuánto lleva la atención',
            'description_hint' => 'Ayuda al negocio a completarlo bien',
            'unit' => 'Unidad',
            'unit_placeholder' => 'Ej. min',
            'unit_hint' => 'Se muestra junto al valor',
            'multiple' => 'Valores',
            'options' => 'Opciones de la lista',
            'options_placeholder' => 'Ej. Chico, Mediano, Grande',
            'options_hint' => 'Separadas por coma. Solo se usan si el tipo de dato es una lista.',
            'order' => 'Orden',
            'order_hint' => 'En qué posición se ofrece al armar un tipo de servicio',
            'status' => 'Estado',
        ],
    ],

    'business_sector' => [
        'search_placeholder' => 'Buscar por clave o nombre',
        'search_label' => 'Buscar rubro',
        'singular' => 'rubro',
        'plural' => 'rubros',
        'create' => 'Crear rubro',
        'new' => 'Nuevo',
        'new_title' => 'Nuevo rubro',
        'edit_title' => 'Editar',
        'empty' => 'No hay rubros que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Clave',
            'name' => 'Nombre',
            'description' => 'Descripción',
            'order' => 'Orden',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
        ],

        'fields' => [
            'code' => 'Clave',
            'code_hint' => 'Sin espacios ni acentos: salud, gastronomia',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Gastronomía',
            'description' => 'Descripción',
            'description_placeholder' => 'Ej. Comida y bebida, para el local o para llevar',
            'description_hint' => 'Ayuda al negocio a elegir bien su rubro',
            'order' => 'Orden',
            'order_hint' => 'En qué posición se le ofrece al negocio',
            'status' => 'Estado',
        ],
    ],

    'business_activity' => [
        'search_placeholder' => 'Buscar por clave, nombre o rubro',
        'search_label' => 'Buscar actividad',
        'singular' => 'actividad',
        'plural' => 'actividades',
        'create' => 'Crear actividad',
        'new' => 'Nueva',
        'new_title' => 'Nueva actividad',
        'edit_title' => 'Editar',
        'empty' => 'No hay actividades que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Clave',
            'name' => 'Nombre',
            'sector' => 'Rubro',
            'order' => 'Orden',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        'fields' => [
            'code' => 'Clave',
            'code_hint' => 'Sin espacios ni acentos: farmacia, panaderia',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Panadería',
            'sector' => 'Rubro',
            'sector_placeholder' => 'Elegir rubro',
            'description' => 'Descripción',
            'description_placeholder' => 'Ej. Elaboración y venta de pan y facturas',
            'description_hint' => 'Con qué palabras el negocio se reconoce en esta actividad',
            'order' => 'Orden',
            'order_hint' => 'En qué posición se ofrece dentro del rubro',
            'status' => 'Estado',
        ],
    ],

];
