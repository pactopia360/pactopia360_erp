<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Líneas de idioma de validación
    |--------------------------------------------------------------------------
    |
    | Las siguientes líneas contienen los mensajes de error por defecto usados
    | por la clase validadora. Algunas reglas tienen múltiples versiones como
    | las de tamaño. Siéntete libre de ajustar cada uno de estos mensajes aquí.
    |
    */

    'accepted'             => 'El :attribute debe ser aceptado.',
    'accepted_if'          => 'El :attribute debe ser aceptado cuando :other sea :value.',
    'active_url'           => 'El :attribute no es una URL válida.',
    'after'                => 'El :attribute debe ser una fecha posterior a :date.',
    'after_or_equal'       => 'El :attribute debe ser una fecha posterior o igual a :date.',
    'alpha'                => 'El :attribute solo puede contener letras.',
    'alpha_dash'           => 'El :attribute solo puede contener letras, números, guiones y guiones bajos.',
    'alpha_num'            => 'El :attribute solo puede contener letras y números.',
    'array'                => 'El :attribute debe ser un arreglo.',
    'ascii'                => 'El :attribute solo debe contener caracteres alfanuméricos y símbolos de un solo byte.',
    'before'               => 'El :attribute debe ser una fecha anterior a :date.',
    'before_or_equal'      => 'El :attribute debe ser una fecha anterior o igual a :date.',
    'between'              => [
        'array'   => 'El :attribute debe tener entre :min y :max elementos.',
        'file'    => 'El :attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => 'El :attribute debe estar entre :min y :max.',
        'string'  => 'El :attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean'              => 'El campo :attribute debe ser verdadero o falso.',
    'can'                  => 'El :attribute contiene un valor no autorizado.',
    'confirmed'            => 'La confirmación de :attribute no coincide.',
    'current_password'     => 'La contraseña es incorrecta.',
    'date'                 => 'El :attribute no es una fecha válida.',
    'date_equals'          => 'El :attribute debe ser una fecha igual a :date.',
    'date_format'          => 'El :attribute no coincide con el formato :format.',
    'decimal'              => 'El :attribute debe tener :decimal decimales.',
    'declined'             => 'El :attribute debe ser rechazado.',
    'declined_if'          => 'El :attribute debe ser rechazado cuando :other sea :value.',
    'different'            => 'El :attribute y :other deben ser diferentes.',
    'digits'               => 'El :attribute debe tener :digits dígitos.',
    'digits_between'       => 'El :attribute debe tener entre :min y :max dígitos.',
    'dimensions'           => 'El :attribute tiene dimensiones de imagen inválidas.',
    'distinct'             => 'El campo :attribute tiene un valor duplicado.',
    'doesnt_end_with'      => 'El :attribute no debe terminar con: :values.',
    'doesnt_start_with'    => 'El :attribute no debe comenzar con: :values.',
    'email'                => 'Escribe un correo válido.',
    'ends_with'            => 'El :attribute debe terminar con uno de los siguientes valores: :values.',
    'enum'                 => 'El :attribute seleccionado no es válido.',
    'exists'               => 'El :attribute seleccionado no es válido.',
    'extensions'           => 'El :attribute debe tener alguna de las extensiones: :values.',
    'file'                 => 'El :attribute debe ser un archivo.',
    'filled'               => 'El campo :attribute es obligatorio.',
    'gt'                   => [
        'array'   => 'El :attribute debe tener más de :value elementos.',
        'file'    => 'El :attribute debe ser mayor que :value kilobytes.',
        'numeric' => 'El :attribute debe ser mayor que :value.',
        'string'  => 'El :attribute debe ser mayor que :value caracteres.',
    ],
    'gte'                  => [
        'array'   => 'El :attribute debe tener :value elementos o más.',
        'file'    => 'El :attribute debe ser mayor o igual que :value kilobytes.',
        'numeric' => 'El :attribute debe ser mayor o igual que :value.',
        'string'  => 'El :attribute debe ser mayor o igual que :value caracteres.',
    ],
    'hex_color'            => 'El :attribute debe ser un color hexadecimal válido.',
    'image'                => 'El :attribute debe ser una imagen.',
    'in'                   => 'El :attribute seleccionado no es válido.',
    'in_array'             => 'El campo :attribute no existe en :other.',
    'integer'              => 'El :attribute debe ser un número entero.',
    'ip'                   => 'El :attribute debe ser una dirección IP válida.',
    'ipv4'                 => 'El :attribute debe ser una dirección IPv4 válida.',
    'ipv6'                 => 'El :attribute debe ser una dirección IPv6 válida.',
    'json'                 => 'El :attribute debe ser una cadena JSON válida.',
    'lowercase'            => 'El :attribute debe estar en minúsculas.',
    'lt'                   => [
        'array'   => 'El :attribute debe tener menos de :value elementos.',
        'file'    => 'El :attribute debe ser menor que :value kilobytes.',
        'numeric' => 'El :attribute debe ser menor que :value.',
        'string'  => 'El :attribute debe ser menor que :value caracteres.',
    ],
    'lte'                  => [
        'array'   => 'El :attribute no debe tener más de :value elementos.',
        'file'    => 'El :attribute debe ser menor o igual que :value kilobytes.',
        'numeric' => 'El :attribute debe ser menor o igual que :value.',
        'string'  => 'El :attribute debe ser menor o igual que :value caracteres.',
    ],
    'mac_address'          => 'El :attribute debe ser una dirección MAC válida.',
    'max'                  => [
        'array'   => 'El :attribute no debe tener más de :max elementos.',
        'file'    => 'El :attribute no debe ser mayor que :max kilobytes.',
        'numeric' => 'El :attribute no debe ser mayor que :max.',
        'string'  => 'El :attribute no debe tener más de :max caracteres.',
    ],
    'max_digits'           => 'El :attribute no debe tener más de :max dígitos.',
    'mimes'                => 'El :attribute debe ser un archivo de tipo: :values.',
    'mimetypes'            => 'El :attribute debe ser un archivo de tipo: :values.',
    'min'                  => [
        'array'   => 'El :attribute debe tener al menos :min elementos.',
        'file'    => 'El :attribute debe tener al menos :min kilobytes.',
        'numeric' => 'El :attribute debe ser al menos :min.',
        'string'  => 'El :attribute debe tener al menos :min caracteres.',
    ],
    'min_digits'           => 'El :attribute debe tener al menos :min dígitos.',
    'missing'              => 'El campo :attribute debe faltar.',
    'missing_if'           => 'El campo :attribute debe faltar cuando :other sea :value.',
    'missing_unless'       => 'El campo :attribute debe faltar a menos que :other sea :value.',
    'missing_with'         => 'El campo :attribute debe faltar cuando :values esté presente.',
    'missing_with_all'     => 'El campo :attribute debe faltar cuando :values estén presentes.',
    'multiple_of'          => 'El :attribute debe ser un múltiplo de :value.',
    'not_in'               => 'El :attribute seleccionado no es válido.',
    'not_regex'            => 'El formato de :attribute no es válido.',
    'numeric'              => 'El :attribute debe ser un número.',
    'password'             => [
        'letters'       => 'El :attribute debe contener al menos una letra.',
        'mixed'         => 'El :attribute debe contener al menos una letra mayúscula y una minúscula.',
        'numbers'       => 'El :attribute debe contener al menos un número.',
        'symbols'       => 'El :attribute debe contener al menos un símbolo.',
        'uncompromised' => 'La contraseña indicada apareció en una filtración de datos. Elige una diferente.',
    ],
    'present'              => 'El campo :attribute debe estar presente.',
    'present_if'           => 'El campo :attribute debe estar presente cuando :other sea :value.',
    'present_unless'       => 'El campo :attribute debe estar presente a menos que :other sea :value.',
    'present_with'         => 'El campo :attribute debe estar presente cuando :values esté presente.',
    'present_with_all'     => 'El campo :attribute debe estar presente cuando :values estén presentes.',
    'prohibited'           => 'El campo :attribute está prohibido.',
    'prohibited_if'        => 'El campo :attribute está prohibido cuando :other sea :value.',
    'prohibited_unless'    => 'El campo :attribute está prohibido a menos que :other esté en :values.',
    'prohibits'            => 'El campo :attribute prohíbe que :other esté presente.',
    'regex'                => 'El formato de :attribute no es válido.',
    'required'             => 'Este campo es obligatorio.',
    'required_array_keys'  => 'El :attribute debe contener entradas para: :values.',
    'required_if'          => 'Este campo es obligatorio cuando :other es :value.',
    'required_if_accepted' => 'Este campo es obligatorio cuando :other ha sido aceptado.',
    'required_unless'      => 'Este campo es obligatorio a menos que :other esté en :values.',
    'required_with'        => 'Este campo es obligatorio cuando :values está presente.',
    'required_with_all'    => 'Este campo es obligatorio cuando :values están presentes.',
    'required_without'     => 'Este campo es obligatorio cuando :values no está presente.',
    'required_without_all' => 'Este campo es obligatorio cuando ninguno de :values están presentes.',
    'same'                 => 'El :attribute y :other deben coincidir.',
    'size'                 => [
        'array'   => 'El :attribute debe contener :size elementos.',
        'file'    => 'El :attribute debe tener :size kilobytes.',
        'numeric' => 'El :attribute debe ser :size.',
        'string'  => 'El :attribute debe tener :size caracteres.',
    ],
    'starts_with'          => 'El :attribute debe comenzar con uno de los siguientes: :values.',
    'string'               => 'El :attribute debe ser una cadena.',
    'timezone'             => 'El :attribute debe ser una zona horaria válida.',
    'unique'               => 'Este :attribute ya está en uso.',
    'uploaded'             => 'No se pudo subir el archivo.',
    'uppercase'            => 'El :attribute debe estar en mayúsculas.',
    'url'                  => 'El formato de :attribute no es válido.',
    'ulid'                 => 'El :attribute debe ser un ULID válido.',
    'uuid'                 => 'El :attribute debe ser un UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Mensajes personalizados para atributos específicos
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'email' => [
            'required' => 'Ingresa tu correo electrónico.',
            'email'    => 'Escribe un correo válido (ej. nombre@dominio.com).',
            'max'      => 'El correo no debe exceder 150 caracteres.',
        ],
        'code' => [
            'required' => 'Ingresa el código que te enviamos.',
            'digits'   => 'El código debe tener :digits dígitos.',
        ],
        'telefono' => [
            'required' => 'Ingresa tu teléfono.',
            'max'      => 'El teléfono no debe exceder :max caracteres.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Atributos legibles (se usan dentro de los mensajes)
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'email'         => 'correo electrónico',
        'password'      => 'contraseña',
        'password_confirmation' => 'confirmación de contraseña',
        'login'         => 'correo o RFC',
        'identifier'    => 'correo o RFC',
        'telefono'      => 'teléfono',
        'country_code'  => 'código de país',
        'code'          => 'código',
        'rfc'           => 'RFC',
        'name'          => 'nombre',
        'razon_social'  => 'razón social',
        'file'          => 'archivo',
    ],

];
