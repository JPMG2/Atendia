<?php

declare(strict_types=1);

return [
    'entities' => [
        'user' => 'Usuario',
        'company' => 'Empresa',
        'currency' => 'Moneda',
        'department' => 'Departamento',
        'sequence' => 'Secuencia',
        'country' => 'País',
        'province' => 'Provincia',
        'region' => 'Región',
        'tax_condition' => 'Condición fiscal',
        'social_network' => 'Red social',
        'status' => 'Estado',
        'blood_type' => 'Tipo sangre',
        'document_type' => 'Tipo documento',
        'gender' => 'Género',
        'marital_status' => 'Estado civil',
        'occupation' => 'Ocupación',
        'record' => 'Registro',
    ],
    'created' => [
        'male' => ':entity creado correctamente',
        'female' => ':entity creada correctamente',
    ],
    'updated' => [
        'male' => ':entity actualizado correctamente',
        'female' => ':entity actualizada correctamente',
    ],
    'deleted' => [
        'male' => ':entity eliminado correctamente',
        'female' => ':entity eliminada correctamente',
    ],
    'not_created' => 'Registro no creado',
    'not_updated' => 'Registro no actualizado',
    'not_deleted' => 'Registro no eliminado',
    'no_changes' => 'No se realizaron cambios en el registro.',
    // Sin imperativo a propósito: así no necesita override de voseo en es_AR.
    'not_found' => 'El registro ya no existe.',
    'invalid_action' => 'Acción no permitida: :action',
];
