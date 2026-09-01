<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Correos — copy de los mensajes que salen del sistema
|--------------------------------------------------------------------------
|
| Base NEUTRA (tuteo). Está escrito sin verbos en segunda persona, así que
| `es_AR` no necesita override de voseo; si se agrega uno, va parcial.
|
*/

return [

    'new_company' => [
        'subject' => 'Tu compañía quedó registrada',
        'greeting' => 'La compañía quedó registrada',
        'intro' => 'Estos son los datos que van a encabezar las facturas y el pie del sitio.',
        'legal_name' => 'Razón social',
        'tax_id' => 'Identificación fiscal',
        'address' => 'Dirección',
        'action' => 'Ver la configuración',
        'outro' => 'Si algo no quedó bien, se corrige desde la pantalla de Compañía.',
        'salutation' => 'Gracias,',
    ],

];
