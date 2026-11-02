<?php

declare(strict_types=1);

return [
    'title' => 'Tasa de cambio',
    'sub' => 'Bolívares por dólar con los que se cobra. Se consulta sola cada hora, de :from a :to (:city).',
    'checked_at' => 'Consultada el :date a las :time',
    'never_checked' => 'Todavía no se consultó',

    'sources' => [
        'bcv_page' => 'Página del BCV',
        'dolarapi' => 'dolarapi (oficial BCV)',
        'manual' => 'Carga manual',
    ],

    'alerts' => [
        'fallback_title' => 'La página del BCV no respondió en la última consulta.',
        'fallback_body' => 'Se cobra con la tasa de «:source», fecha valor :date: es la oficial del BCV. No hace falta que hagas nada.',
        'manual_title' => 'Se cobra con la tasa que cargaste a mano.',
        'manual_body' => 'Fecha valor :date. La reemplaza la primera fuente que traiga una fecha valor más nueva.',
        'down_title' => 'No hay una tasa al día.',
        'down_body' => 'Se sigue cobrando con la fecha valor :date. Cárgala a mano abajo; también está en el Tablero de avisos.',
        'down_waiting_body' => 'Se sigue cobrando con la fecha valor :date. Hay un valor más nuevo esperando tu confirmación arriba: confírmalo si es correcto, o carga otro abajo.',
        'sources_title' => 'Fallaron las dos fuentes en la última consulta.',
        'sources_body' => 'Se cobra con la fecha valor :date, que todavía está al día. Se vuelve a probar en la próxima hora; si se pone vieja, te avisa.',
        'none_title' => 'Todavía no hay ninguna tasa guardada.',
        'none_body' => 'La primera consulta automática la trae sola. Si la necesitas antes, cárgala a mano abajo.',
    ],

    'tiles' => [
        'charging' => 'Tasa con la que se cobra',
        'parallel' => 'Paralelo (solo referencia)',
        'plan' => 'Plan :plan hoy',
        'unit' => 'Bs/USD',
        'source' => 'Fuente: :source',
        'stale' => 'Vieja · fecha valor :date',
        'value_date' => 'Fecha valor :date',
        'parallel_note' => 'No se usa para cobrar',
        'parallel_gap' => ':percent % arriba de la oficial',
        'parallel_missing' => 'Sin dato: dolarapi no lo trajo',
        'none' => 'Sin tasa guardada',
        'plan_line' => 'USD :dollars × la tasa con la que se cobra',
    ],

    'trend' => [
        'line' => ':days días: :from → :to (:change %)',
        'official' => 'Tendencia de la oficial en los últimos 30 días',
        'parallel' => 'Tendencia del paralelo en los últimos 30 días',
    ],

    'chain' => [
        'title' => 'De dónde sale la tasa',
        'sub' => 'Se prueba en este orden; se usa la primera que responde con un valor razonable.',
        'bcv' => 'Página del BCV',
        'bcv_note' => 'bcv.org.ve · lee el recuadro del dólar y la fecha valor',
        'api' => 'dolarapi, oficial del BCV',
        'api_note' => 've.dolarapi.com · mismo número, otra vía',
        'manual' => 'Carga manual',
        'manual_note' => 'La tuya, si fallan las dos. Vale hasta que una fuente vuelva con una fecha valor más nueva.',
        'parallel' => 'Paralelo (dolarapi)',
        'parallel_note' => 'Se guarda y se muestra; nunca cobra',
        'answered' => 'Respondió',
        'failed' => 'Falló',
        'in_use' => 'En uso',
        'not_needed' => 'No hace falta',
        'waiting_you' => 'Esperándote',
        'standby' => 'Lista si hace falta',
        'reference' => 'Referencia',
        'not_checked' => 'Sin consultar',
    ],

    'waiting' => [
        'title' => 'Un valor espera tu confirmación',
        'body' => 'El :date llegó :rate Bs/USD de :source, un :percent % lejos de la que se cobra. No cobra hasta que lo confirmes.',
        'confirm' => 'Confirmar este valor',
        'confirm_title' => 'Confirmar la tasa',
        'confirm_message' => 'Desde ahora se cobra con :rate Bs/USD.',
        'confirmed' => 'Listo. Esa tasa ya cobra.',
        'changed' => 'Ese valor cambió mientras lo mirabas. Revisa el nuevo antes de confirmarlo.',
    ],

    'manual' => [
        'title' => 'Cargar la tasa a mano',
        'sub' => 'Para cuando ninguna fuente responde. Queda registrada con tu nombre.',
        'rate' => 'Bs por dólar',
        'date' => 'Fecha valor',
        'hint' => 'Si se aleja más de :percent % de la tasa anterior a esa fecha, te pide confirmarla antes de usarla.',
        'submit' => 'Usar esta tasa',
        'saved' => 'Listo. Desde ahora se cobra con esa tasa.',
        'saved_future' => 'Guardada. Empieza a cobrar el :date.',
        'saved_past' => 'Guardada para el :date. La de hoy no cambia: hay una fecha valor más nueva.',
        'no_permission' => 'Cargar la tasa a mano pide el permiso «Cargar la tasa de cambio a mano o confirmar un salto».',
        'jump_title' => 'Es un salto grande',
        'jump_message' => 'Leí :rate Bs/USD, que se aleja :percent % de la tasa anterior. ¿La usas igual?',
        'jump_accept' => 'Usar esta tasa',
    ],

    'history' => [
        'title' => 'Historial',
        'sub' => 'Una fila por fecha valor. Cada pago guarda la tasa con la que se cobró.',
        'date' => 'Fecha valor',
        'official' => 'Oficial (cobra)',
        'source' => 'Fuente',
        'parallel' => 'Paralelo',
        'saved' => 'Guardada',
        'by' => 'Manual · :name',
        'waiting' => 'Espera confirmación',
        'empty' => 'Todavía no hay tasas guardadas.',
    ],

    // What the client transfers, in the receipt panel of Mis pagos.
    'receipt' => [
        'title' => 'Cuánto transferir en bolívares',
        'body' => 'Transfiere :amount: tasa oficial del BCV del :date (:rate Bs/USD).',
    ],

    // Shown next to money on Mis pagos and Pagos.
    'toggle' => [
        'label' => 'Ver montos en',
        'usd' => 'USD',
        'ves' => 'Bs',
        'no_rate' => 'Sin tasa guardada para este pago',
        'today' => 'a la tasa oficial del BCV del :date',
        'paid_at' => 'a la tasa del :date',
    ],
];
