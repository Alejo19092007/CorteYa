<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\Turno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros de GET /api/turnos: el teléfono del cliente es obligatorio y el estado, opcional.
 */
class ListarTurnosApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->telefono)) {
            $this->merge(['telefono' => Cliente::normalizarTelefono($this->telefono) ?? $this->telefono]);
        }
    }

    public function rules(): array
    {
        return [
            'telefono' => ['required', 'regex:/^57[0-9]{10}$/'],
            'estado' => ['nullable', Rule::in([Turno::PENDIENTE, Turno::CONFIRMADO, Turno::ATENDIDO, Turno::CANCELADO, Turno::NO_ASISTIO])],
        ];
    }
}
