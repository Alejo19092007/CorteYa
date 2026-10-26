<?php

// Mensajes de validación en español (Laravel 12 no trae carpeta "lang").
// Se usan en los Form Requests; "attributes" traduce el nombre de cada campo.
return [
    'accepted' => 'Debes aceptar :attribute.',
    'after' => ':attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => ':attribute debe ser una fecha igual o posterior a :date.',
    'array' => ':attribute debe ser una lista.',
    'before' => ':attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => ':attribute debe ser una fecha igual o anterior a :date.',
    'boolean' => ':attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'date' => ':attribute no es una fecha válida.',
    'date_format' => ':attribute no tiene el formato :format.',
    'decimal' => ':attribute debe tener :decimal decimales.',
    'digits' => ':attribute debe tener :digits dígitos.',
    'digits_between' => ':attribute debe tener entre :min y :max dígitos.',
    'email' => ':attribute debe ser un correo válido.',
    'exists' => ':attribute seleccionado no es válido.',
    'gt' => [
        'numeric' => ':attribute debe ser mayor que :value.',
        'string' => ':attribute debe tener más de :value caracteres.',
    ],
    'in' => ':attribute seleccionado no es válido.',
    'integer' => ':attribute debe ser un número entero.',
    'max' => [
        'numeric' => ':attribute no puede ser mayor que :max.',
        'string' => ':attribute no puede tener más de :max caracteres.',
        'array' => ':attribute no puede tener más de :max elementos.',
    ],
    'min' => [
        'numeric' => ':attribute debe ser al menos :min.',
        'string' => ':attribute debe tener al menos :min caracteres.',
        'array' => ':attribute debe tener al menos :min elementos.',
    ],
    'numeric' => ':attribute debe ser un número.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'same' => ':attribute y :other deben coincidir.',
    'string' => ':attribute debe ser texto.',
    'unique' => ':attribute ya está registrado.',

    'custom' => [],

    // Nombres legibles de los campos del proyecto
    'attributes' => [
        'email' => 'correo',
        'password' => 'contraseña',
        'name' => 'nombre',
        'nombre' => 'nombre',
        'telefono' => 'teléfono',
        'fecha' => 'fecha',
        'hora_inicio' => 'hora de inicio',
        'hora_fin' => 'hora de fin',
        'barbero_id' => 'barbero',
        'cliente_id' => 'cliente',
        'servicios' => 'servicios',
        'duracion_min' => 'duración',
        'precio' => 'precio',
        'estado' => 'estado',
        'dia_semana' => 'día de la semana',
    ],
];
