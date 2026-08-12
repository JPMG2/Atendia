<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'El :attribute debe ser acceptado.',
    'accepted_if' => 'El :attribute debe ser acceptado cuando :other es :value.',
    'active_url' => 'El :attribute debe ser una URL válida.',
    'after' => 'El :attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => 'El :attribute debe ser una fecha posterior o igual a :date.',
    'alpha' => 'El :attribute debe contener solo letras.',
    'alpha_dash' => 'El :attribute debe contener solo letras, números, guiones y guiones bajos.',
    'alpha_num' => 'El :attribute debe contener solo letras y números.',
    'any_of' => 'El :attribute es inválido.',
    'array' => 'El :attribute debe ser un array.',
    'ascii' => 'El :attribute debe contener solo caracteres alfanuméricos de un solo byte y símbolos.',
    'before' => 'El :attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => 'El :attribute debe ser una fecha anterior o igual a :date.',
    'between' => [
        'array' => 'El :attribute debe tener entre :min y :max elementos.',
        'file' => 'El :attribute debe tener entre :min y :max kilobytes.',
        'numeric' => 'El :attribute debe estar entre :min y :max.',
        'string' => 'El :attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'El :attribute debe ser verdadero o falso.',
    'can' => 'El :attribute contiene un valor no autorizado.',
    'confirmed' => 'La confirmación del :attribute no coincide.',
    'contains' => 'El :attribute está faltando un valor requerido.',
    'current_password' => 'La contraseña es incorrecta.',
    'date' => 'El :attribute debe ser una fecha válida.',
    'date_equals' => 'El :attribute debe ser una fecha igual a :date.',
    'date_format' => 'El :attribute debe coincidir con el formato :format.',
    'decimal' => 'El :attribute debe tener :decimal lugares decimales.',
    'declined' => 'El :attribute debe ser declinado.',
    'declined_if' => 'El :attribute debe ser declinado cuando :other es :value.',
    'different' => 'El :attribute y :other deben ser diferentes.',
    'digits' => 'El :attribute debe tener :digits dígitos.',
    'digits_between' => 'El :attribute debe tener entre :min y :max dígitos.',
    'dimensions' => 'El :attribute tiene dimensiones de imagen inválidas.',
    'distinct' => 'El :attribute tiene un valor duplicado.',
    'doesnt_contain' => 'El :attribute no debe contener ninguno de los siguientes: :values.',
    'doesnt_end_with' => 'El :attribute no debe terminar con uno de los siguientes: :values.',
    'doesnt_start_with' => 'El :attribute no debe comenzar con uno de los siguientes: :values.',
    'email' => 'El :attribute debe ser una dirección de correo electrónico válida.',
    'encoding' => 'El :attribute debe estar codificado en :encoding.',
    'ends_with' => 'El :attribute debe terminar con uno de los siguientes: :values.',
    'enum' => 'La opción seleccionada para :attribute es inválida.',
    'exists' => 'La opción seleccionada para :attribute es inválida.',
    'extensions' => 'El :attribute debe tener una de las siguientes extensiones: :values.',
    'file' => 'El :attribute debe ser un archivo.',
    'filled' => 'El :attribute debe tener un valor.',
    'gt' => [
        'array' => 'El :attribute debe tener más de :value elementos.',
        'file' => 'El :attribute debe ser mayor que :value kilobytes.',
        'numeric' => 'El :attribute debe ser mayor que :value.',
        'string' => 'El :attribute debe tener más de :value caracteres.',
    ],
    'gte' => [
        'array' => 'El :attribute debe tener :value elementos o más.',
        'file' => 'El :attribute debe ser mayor o igual a :value kilobytes.',
        'numeric' => 'El :attribute debe ser mayor o igual a :value.',
        'string' => 'El :attribute debe tener :value caracteres o más.',
    ],
    'hex_color' => 'El :attribute debe ser un color hexadecimal válido.',
    'image' => 'El :attribute debe ser una imagen.',
    'in' => 'La opción seleccionada para :attribute es inválida.',
    'in_array' => 'El :attribute debe existir en :other.',
    'in_array_keys' => 'El :attribute debe contener al menos una de las siguientes claves: :values.',
    'integer' => 'El :attribute debe ser un número entero.',
    'ip' => 'El :attribute debe ser una dirección IP válida.',
    'ipv4' => 'El :attribute debe ser una dirección IPv4 válida.',
    'ipv6' => 'El :attribute debe ser una dirección IPv6 válida.',
    'json' => 'El :attribute debe ser una cadena JSON válida.',
    'list' => 'El :attribute debe ser una lista.',
    'lowercase' => 'El :attribute debe estar en minúsculas.',
    'lt' => [
        'array' => 'El :attribute debe tener menos de :value elementos.',
        'file' => 'El :attribute debe ser menor que :value kilobytes.',
        'numeric' => 'El :attribute debe ser menor que :value.',
        'string' => 'El :attribute debe tener menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'El :attribute no debe tener más de :value elementos.',
        'file' => 'El :attribute debe ser menor o igual a :value kilobytes.',
        'numeric' => 'El :attribute debe ser menor o igual a :value.',
        'string' => 'El :attribute debe tener :value caracteres o menos.',
    ],
    'mac_address' => 'El :attribute debe ser una dirección MAC válida.',
    'max' => [
        'array' => 'El :attribute no debe tener más de :max elementos.',
        'file' => 'El :attribute no debe ser mayor que :max kilobytes.',
        'numeric' => 'El :attribute no debe ser mayor que :max.',
        'string' => 'El :attribute no debe tener más de :max caracteres.',
    ],
    'max_digits' => 'El :attribute no debe tener más de :max dígitos.',
    'mimes' => 'El :attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => 'El :attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'array' => 'El :attribute debe tener al menos :min elementos.',
        'file' => 'El :attribute debe ser al menos :min kilobytes.',
        'numeric' => 'El :attribute debe ser al menos :min.',
        'string' => 'El :attribute debe tener al menos :min caracteres.',
    ],
    'min_digits' => 'El :attribute debe tener al menos :min dígitos.',
    'missing' => 'El :attribute debe estar ausente.',
    'missing_if' => 'El :attribute debe estar ausente cuando :other es :value.',
    'missing_unless' => 'El :attribute debe estar ausente a menos que :other sea :value.',
    'missing_with' => 'El :attribute debe estar ausente cuando :values está presente.',
    'missing_with_all' => 'El :attribute debe estar ausente cuando :values están presentes.',
    'multiple_of' => 'El :attribute debe ser múltiplo de :value.',
    'not_in' => 'La opción seleccionada para :attribute es inválida.',
    'not_regex' => 'El formato del campo :attribute es inválido.',
    'numeric' => 'El :attribute debe ser un número.',
    'password' => [
        'letters' => 'El :attribute debe contener al menos una letra.',
        'mixed' => 'El :attribute debe contener al menos una letra mayúscula y una minúscula.',
        'numbers' => 'El :attribute debe contener al menos un número.',
        'symbols' => 'El :attribute debe contener al menos un símbolo.',
        'uncompromised' => 'La opción proporcionada para :attribute ha aparecido en una filtración de datos. Por favor, elija una opción diferente para :attribute.',
    ],
    'present' => 'El :attribute debe estar presente.',
    'present_if' => 'El :attribute debe estar presente cuando :other es :value.',
    'present_unless' => 'El :attribute debe estar presente a menos que :other sea :value.',
    'present_with' => 'El :attribute debe estar presente cuando :values está presente.',
    'present_with_all' => 'El :attribute debe estar presente cuando :values están presentes.',
    'prohibited' => 'El :attribute está prohibido.',
    'prohibited_if' => 'El :attribute está prohibido cuando :other es :value.',
    'prohibited_if_accepted' => 'El :attribute está prohibido cuando :other es aceptado.',
    'prohibited_if_declined' => 'El :attribute está prohibido cuando :other es rechazado.',
    'prohibited_unless' => 'El :attribute está prohibido a menos que :other esté en :values.',
    'prohibits' => 'El :attribute impide que :other esté presente.',
    'regex' => 'El formato del campo :attribute es inválido.',
    'required' => 'El :attribute es obligatorio.',
    'required_array_keys' => 'El :attribute debe contener entradas para: :values.',
    'required_if' => 'El :attribute es obligatorio cuando :other es :value.',
    'required_if_accepted' => 'El :attribute es obligatorio cuando :other es aceptado.',
    'required_if_declined' => 'El :attribute es obligatorio cuando :other es rechazado.',
    'required_unless' => 'El :attribute es obligatorio a menos que :other esté en :values.',
    'required_with' => 'El :attribute es obligatorio cuando :values está presente.',
    'required_with_all' => 'El :attribute es obligatorio cuando :values están presentes.',
    'required_without' => 'El :attribute es obligatorio cuando :values no está presente.',
    'required_without_all' => 'El :attribute es obligatorio cuando ninguno de :values está presente.',
    'same' => 'El :attribute debe coincidir con :other.',
    'size' => [
        'array' => 'El :attribute debe contener :size elementos.',
        'file' => 'El :attribute debe ser :size kilobytes.',
        'numeric' => 'El :attribute debe ser :size.',
        'string' => 'El :attribute debe tener :size caracteres.',
    ],
    'starts_with' => 'El :attribute debe comenzar con uno de los siguientes: :values.',
    'string' => 'El :attribute debe ser una cadena.',
    'timezone' => 'El :attribute debe ser una zona horaria válida.',
    'unique' => 'El :attribute ya ha sido tomado.',
    'uploaded' => 'El :attribute falló al subir.',
    'uppercase' => 'El :attribute debe estar en mayúsculas.',
    'url' => 'El :attribute debe ser una URL válida.',
    'ulid' => 'El :attribute debe ser un ULID válido.',
    'uuid' => 'El :attribute debe ser un UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
