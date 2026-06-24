<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Locales soportados
    |--------------------------------------------------------------------------
    |
    | Lista blanca de locales que la app puede activar. Cualquier valor fuera
    | de esta lista (venga de la sesión, la URL o la geolocalización) cae a
    | 'default'. 'es' es el neutro (tuteo) y sirve de fallback para el resto.
    |
    */

    'supported' => ['es', 'es_AR', 'es_VE'],

    'default' => 'es',

    /*
    |--------------------------------------------------------------------------
    | Mapa país → locale
    |--------------------------------------------------------------------------
    |
    | Código ISO de país (el que devuelve stevebauman/location como
    | countryCode) mapeado al locale que le servimos. Lo que no esté acá
    | usa el neutro 'es'. La geolocalización SUGIERE; la elección explícita
    | del usuario (guardada en sesión) siempre tiene prioridad.
    |
    */

    'country_map' => [
        'AR' => 'es_AR', // Argentina  → voseo
        'UY' => 'es_AR', // Uruguay    → voseo
        'VE' => 'es_VE', // Venezuela  → neutro + guiños
    ],

    /*
    |--------------------------------------------------------------------------
    | Etiquetas para el selector manual
    |--------------------------------------------------------------------------
    */

    'labels' => [
        'es' => 'Internacional',
        'es_AR' => 'Argentina',
        'es_VE' => 'Venezuela',
    ],

];
