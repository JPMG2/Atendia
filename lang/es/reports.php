<?php

declare(strict_types=1);

// Reportes: los botones de exportar y el encabezado de cada archivo.
return [
    'buttons' => [
        'pdf' => 'Imprimir',
        'xlsx' => 'Excel',
        'csv' => 'CSV',
    ],
    'aria' => [
        'pdf' => 'Abrir para imprimir o guardar en PDF',
        'xlsx' => 'Descargar en Excel',
        'csv' => 'Descargar en CSV',
    ],
    'generated' => 'Generado el :date',

    'ai_spend' => [
        'title' => 'Consumo de IA por negocio',
        'filename' => 'consumo-ia',
        'cost' => 'Costo (USD)',
        'per_thread' => 'Costo por conversación (USD)',
        'unpriced' => 'Llamadas sin precio',
    ],

    'company' => [
        'title' => 'Datos de la compañía',
        'filename' => 'compania',
        'field' => 'Dato',
        'value' => 'Valor',
    ],
];
