<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Turno pedido por el bot. El cliente se identifica por su teléfono de WhatsApp (no por un id).
 * Como en el panel, NO se piden hora_fin ni precio_total: los calcula el sistema (R2).
 */
class StoreTurnoApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controlan auth:sanctum y role:admin en la ruta
    }

    /** Acepta "3001234567" o "573001234567": se normaliza antes de validar. */
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
            // Solo hace falta si el cliente escribe por primera vez (lo comprueba TurnoService)
            'nombre' => ['nullable', 'string', 'max:100'],
            'barbero_id' => ['required', 'integer', 'exists:barberos,id'],
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*' => ['integer', 'exists:servicios,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'telefono.regex' => 'El teléfono debe ser un celular colombiano de 10 dígitos (con o sin el indicativo 57).',
        ];
    }
}
