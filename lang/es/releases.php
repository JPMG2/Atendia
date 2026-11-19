<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Novedades — lo que trae cada versión, en palabras de cliente
|--------------------------------------------------------------------------
| Una entrada por versión de config/releases.php. Solo lo que un negocio ve
| o usa, y solo lo que es verdad hoy: nada que el producto no haga.
*/

return [
    'title' => 'Novedades',
    'heading' => 'Novedades de :brand',
    'lead' => 'Lo que trae cada versión, de la más nueva a la primera. Así se ve cómo crece :brand.',
    'version' => 'Versión :version',
    'link' => 'v:version',
    'link_label' => 'Versión :version: ver las novedades',
    'current' => 'Actual',
    'kinds' => [
        'major' => 'Versión mayor',
        'minor' => 'Novedades',
        'patch' => 'Mejoras y arreglos',
    ],

    'entries' => [
        '1.0.0' => [
            'title' => 'La primera versión',
            'items' => [
                'Asistente por WhatsApp que responde a toda hora con tus servicios, productos, precios y horarios.',
                'Agenda con varios profesionales: tus clientes reservan por WhatsApp y reciben el enlace para su calendario.',
                'Catálogo de servicios y productos con fotos.',
                'Clientes, conversaciones y estadísticas de tu negocio en un solo panel.',
                'Equipo con accesos propios para cada persona.',
                'Exportación de tus listados a PDF, Excel y CSV.',
                'Pausa del WhatsApp por robo o pérdida del teléfono, sin perder nada.',
            ],
        ],
    ],
];
