<?php

namespace App\Http\Requests;

/**
 * Al reprogramar un turno el cliente no cambia: solo barbero, fecha, hora y servicios.
 */
class UpdateTurnoApiRequest extends StoreTurnoApiRequest
{
    public function rules(): array
    {
        // El cliente y el canal de origen no cambian al reprogramar
        return array_diff_key(parent::rules(), array_flip(['telefono', 'nombre', 'origen']));
    }

    protected function prepareForValidation(): void {}
}
