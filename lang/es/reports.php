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
    'eyebrow' => 'Reporte',
    'page' => 'Página :page de :pages',
    'records' => '{0}Sin registros|{1}1 registro|[2,*]:count registros',

    // El menú de exportar: lo que ve la persona antes de bajar nada.
    'menu' => [
        'button' => 'Exportar',
        'scope_title' => '¿Qué quieres exportar?',
        'view' => 'Lo que estás viendo',
        'view_hint' => ':count · :filters',
        'tax_id_missing' => 'Tus documentos salen sin tu número fiscal.',
        'tax_id_add' => 'Agrégalo',
        'all' => 'Todo, sin filtros',
        'all_hint' => ':count',
        'recommended' => 'Recomendado',
        'format' => 'Formato',
        'note' => 'El documento siempre dice qué filtros tenía, para que nadie lo confunda con la lista completa.',
        'formats' => [
            'pdf' => ['title' => 'Imprimir o guardar PDF', 'hint' => 'Para imprimir, firmar o enviar'],
            'xlsx' => ['title' => 'Excel', 'hint' => 'Para trabajar los datos, con tu encabezado'],
            'csv' => ['title' => 'CSV', 'hint' => 'Solo los datos, para importar a otro sistema'],
        ],
    ],

    // El bloque de alcance del documento y el de quién es.
    'scope' => [
        'title' => 'Alcance del reporte',
        'none' => 'Sin filtros',
        'none_lower' => 'sin filtros',
        'filtered' => 'filtrado',
        'records' => 'Registros',
        'of' => ':shown de :total',
        'timezone' => 'Zona horaria',
    ],
    'letterhead' => [
        'client' => 'Cliente',
        'issuer' => 'Emitido por',
        'tax_id' => 'Número fiscal',
        'address' => 'Dirección',
        'phone' => 'Teléfono',
        'email' => 'Correo',
        'plan' => 'Plan',
    ],

    'customers' => [
        'title' => 'Clientes',
        'filename' => 'clientes',
        'name' => 'Cliente',
        'phone' => 'Teléfono',
        'email' => 'Correo',
        'last_activity' => 'Última actividad',
        'conversations' => 'Conversaciones',
        'opt_in' => 'Permiso de mensajes',
        'birthday' => 'Cumpleaños',
        'anonymous' => 'Sin nombre',
        'yes' => 'Sí',
        'no' => 'No',
        'filter_search' => 'Búsqueda',
        'filter_group' => 'Grupo',
    ],

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
