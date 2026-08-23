<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tipos de dato de un atributo de servicio
|--------------------------------------------------------------------------
|
| Qué clase de valor guarda un `service_attributes` y, por lo tanto, con qué
| control se pinta y con qué regla se valida el valor que carga el negocio.
|
| Va en config y NO en una tabla a propósito: sumar un tipo de dato SIEMPRE
| necesita un renderer y un validador en código, así que una tabla dejaría al
| admin crear un tipo que no sabe dibujarse. Un `data_type` desconocido cae a
| `text`. La columna es un string: mudarlo a tabla algún día no cuesta migración.
|
*/
return [
    'text' => 'Texto',
    'number' => 'Número',
    'money' => 'Precio',
    'boolean' => 'Sí / No',
    'date' => 'Fecha',
    'time' => 'Hora',
    'list' => 'Lista de opciones',
    'image' => 'Imagen',
    'file' => 'Archivo',
];
