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
    'new' => 'Nuevo',
    'new_since' => 'Nuevo desde tu última visita',
    'kinds' => [
        'major' => 'Versión mayor',
        'minor' => 'Novedades',
        'patch' => 'Mejoras y arreglos',
    ],

    'entries' => [
        '1.2.4' => [
            'title' => 'Más fácil de leer',
            'items' => [
                'Los textos grises tienen más contraste, en el tema claro y en el oscuro.',
                'Las horas, los conteos y las etiquetas más pequeñas se leen mejor en el teléfono.',
            ],
        ],
        '1.2.3' => [
            'title' => 'Tarjetas que no se quedan levantadas',
            'items' => [
                'En el teléfono, una tarjeta tocada ya no se queda levantada: el efecto de elevarse queda para el mouse.',
            ],
        ],
        '1.2.2' => [
            'title' => 'El panel, más cómodo en el teléfono',
            'items' => [
                'El menú ocupa justo la pantalla visible: su final ya no queda bajo la barra del navegador.',
                'Los toques responden al instante, sin el destello gris del navegador.',
                'Al desplazar un panel o una lista hasta el final, la página de atrás se queda quieta.',
                'En el tema oscuro, las barras de desplazamiento y el autocompletado también son oscuros.',
            ],
        ],
        '1.2.1' => [
            'title' => 'Datos largos, siempre en su lugar',
            'items' => [
                'Un correo o una dirección muy larga se acomoda en su renglón, en las notificaciones y en las tablas.',
                'Un cliente que aparece solo con su teléfono muestra un ícono de persona en lugar de letras sueltas.',
                'El nombre completo de un contacto se lee al pasar el mouse cuando no entra en la lista.',
            ],
        ],
        '1.2.0' => [
            'title' => 'Lo nuevo, a la vista',
            'items' => [
                'Novedades marca lo que salió desde la última visita.',
                'Un correo con lo que trae cada versión con novedades.',
            ],
        ],
        '1.1.0' => [
            'title' => 'Las novedades llegan solas',
            'items' => [
                'Aviso en la campana cuando sale una versión con novedades, con el enlace para verlas.',
                'Marca «Nuevo» junto a Novedades en el menú hasta abrirlas.',
            ],
        ],
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
