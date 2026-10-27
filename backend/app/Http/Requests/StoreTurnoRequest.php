<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el formato de lo que llega del formulario. Las reglas de negocio (cruces, jornada,
 * cálculo de hora fin y precio) NO se validan aquí: las aplica TurnoService.
 * Tampoco se piden hora_fin ni precio_total: los calcula el sistema (R2).
 */
class StoreTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso ya lo controla el middleware "role:admin" de la ruta
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'barbero_id' => ['required', 'integer', 'exists:barberos,id'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*' => ['integer', 'exists:servicios,id'],
        ];
    }
}
