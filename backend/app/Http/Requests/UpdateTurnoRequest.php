<?php

namespace App\Http\Requests;

/**
 * Al editar se piden los mismos datos que al crear, pero el cliente ya existe: no se puede cambiar por uno
 * nuevo desde aquí (los clientes nuevos se crean al registrar un turno).
 */
class UpdateTurnoRequest extends StoreTurnoRequest
{
    public function rules(): array
    {
        $reglas = array_diff_key(parent::rules(), array_flip(['cliente_nombre', 'cliente_telefono']));
        $reglas['cliente_id'] = ['required', 'integer', 'exists:clientes,id'];

        return $reglas;
    }
}
