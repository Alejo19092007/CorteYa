<?php

namespace App\Http\Requests;

use App\Models\Turno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Estados que se pueden pedir desde los botones de la agenda y del listado.
 * "cancelado" no está: cancelar es otra acción (DELETE) porque aplica reglas distintas.
 */
class CambiarEstadoTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la política TurnoPolicy@cambiarEstado se aplica en la ruta
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in([Turno::CONFIRMADO, Turno::ATENDIDO, Turno::NO_ASISTIO])],
        ];
    }
}
