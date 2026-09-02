<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pantalla de la Compañía (panel admin)
|--------------------------------------------------------------------------
|
| Los datos de Atendia — UN solo registro, no los negocios que contratan el
| servicio. Base neutra (tuteo); lang/es_AR/company.php solo sobreescribe el
| voseo.
*/

return [
    'title' => 'Compañía',
    'subtitle' => 'Los datos de Atendia: encabezan la factura y se muestran en la web.',

    'discard' => 'Descartar',

    // Descartar avisa antes: lo tipeado sin guardar no se puede recuperar.
    'discard_confirm' => [
        'title' => '¿Descartar los cambios?',
        'message' => 'Lo escrito sin guardar en este paso se pierde.',
        'accept' => 'Descartar',
    ],

    'save' => 'Guardar cambios',
    // Sin compañía cargada, guardar el paso 1 es lo que abre el 2: el botón lo dice.
    'save_continue' => 'Guardar y continuar',

    /*
     * Los dos pasos de la carga. El segundo se abre recién cuando la compañía
     * existe, así que el copy del bloqueo tiene que decir qué lo destraba.
     */
    'steps' => [
        'locked_hint' => 'Guarda la configuración principal para abrir este paso.',

        'main' => [
            'label' => 'Configuración principal',
            'desc' => 'Identidad, domicilio, datos fiscales y logo.',
        ],

        'commercial' => [
            'label' => 'Datos comerciales',
            'desc' => 'Contacto público y redes sociales.',
        ],
    ],

    'identity' => [
        'title' => 'Identidad',
        'desc' => 'Cómo se llama la empresa y la frase que la acompaña. Aparece en la web, el panel y la factura.',
    ],

    'tax' => [
        'title' => 'Datos fiscales',
        'desc' => 'Lo que exige la factura: el número que identifica a la empresa y su condición frente al impuesto.',
    ],

    'address' => [
        'title' => 'Domicilio',
        'desc' => 'Dónde está la empresa. El país es el que manda: de él dependen la condición fiscal y el formato del número.',
    ],

    'logo' => [
        'title' => 'Logotipo',
        'desc' => 'SVG o PNG con fondo transparente. Se usa una versión para el tema claro y otra para el oscuro.',
        'light' => 'Logo para fondo claro',
        'dark' => 'Logo para fondo oscuro',
        'upload' => 'Subir archivo',
        'hint' => 'SVG, PNG, WEBP o JPG, hasta 2 MB.',

        // La baja es inmediata, como la de una red: el aviso dice la consecuencia.
        'remove_confirm' => [
            'title' => '¿Quitar el logo?',
            'message' => 'Se elimina del sitio y del pie de página. No se puede deshacer.',
            'accept' => 'Quitar el logo',
        ],
    ],

    'footer' => [
        'title' => 'Pie de la factura',
        'desc' => 'La línea que cierra cada factura emitida.',
    ],

    'contact' => [
        'title' => 'Contacto público',
        'desc' => 'Se muestran en la web, el pie de página y los mensajes automáticos.',
    ],

    'social' => [
        'title' => 'Redes sociales',
        'desc' => 'Agrega las redes donde está la empresa. El orden es el que se ve en el pie de página.',
        'add' => 'Agregar red',
        'remove' => 'Quitar esta red',

        // La baja es inmediata y no hay papelera: el aviso dice la consecuencia,
        // y el botón nombra la acción en vez de un "Aceptar" genérico.
        'remove_confirm' => [
            'title' => '¿Eliminar esta red?',
            'message' => 'Se quita del pie de página y de la web. No se puede deshacer.',
            'accept' => 'Eliminar la red',
        ],
        'network' => 'Red',
        'network_placeholder' => 'Elige una red',
        'url' => 'Enlace o usuario',
        'url_placeholder' => 'https://instagram.com/atendia',
    ],

    'fields' => [
        'legal_name' => 'Razón social',
        'legal_name_placeholder' => 'Atendia S.A.',

        'tagline' => 'Tagline',
        'tagline_hint' => 'Frase corta bajo el logo.',
        'tagline_placeholder' => 'Tu negocio, atendido por IA',

        'tax_id' => 'Identificación fiscal',
        'tax_id_hint' => 'RIF o CUIT, sin puntos ni guiones.',

        'tax_condition' => 'Condición fiscal',
        'tax_condition_placeholder' => 'Elige la condición',

        'region' => 'Región',
        'region_placeholder' => 'Elige la región',

        'country' => 'País',
        'country_placeholder' => 'Elige el país',

        'province' => 'Provincia',
        'province_placeholder' => 'Elige la provincia',

        'address' => 'Dirección',
        'address_placeholder' => 'Av. Siempre Viva 742, piso 3',

        'copyright' => 'Texto del pie',
        'copyright_placeholder' => '© Atendia. Todos los derechos reservados.',

        'email' => 'Email de soporte',
        'email_placeholder' => 'hola@atendia.app',

        'phone' => 'WhatsApp / teléfono',
        'phone_placeholder' => '+54 9 11 5555-1234',

        'web' => 'Sitio web',
        'web_placeholder' => 'https://atendia.app',
    ],
];
