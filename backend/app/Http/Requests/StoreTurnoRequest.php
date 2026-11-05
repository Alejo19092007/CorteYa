<?php

namespace App\Http\Requests;

use App\Models\Cliente;
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

    public function messages(): array
    {
        return [
            'cliente_id.required_without' => 'Elige un cliente de la lista o escribe el nombre de uno nuevo.',
            'cliente_nombre.min' => 'El nombre del cliente debe tener al menos 2 letras.',
            'cliente_telefono.regex' => 'El teléfono debe ser un celular de 10 dígitos (con o sin el indicativo 57).',
        ];
    }

    /** El teléfono del cliente nuevo se acepta con espacios o guiones ("300 123 4567") y se deja como "573001234567". */
    protected function prepareForValidation(): void
    {
        if (is_string($this->cliente_telefono) && trim($this->cliente_telefono) !== '') {
            $this->merge(['cliente_telefono' => Cliente::normalizarTelefono($this->cliente_telefono) ?? $this->cliente_telefono]);
        }
    }

    public function rules(): array
    {
        return [
            // O se elige un cliente de la lista (cliente_id) o se escribe el nombre de uno nuevo (cliente_nombre).
            // El teléfono del cliente nuevo es opcional; si viene debe ser un celular de 10 dígitos con el 57.
            'cliente_id' => ['nullable', 'required_without:cliente_nombre', 'integer', 'exists:clientes,id'],
            'cliente_nombre' => ['nullable', 'string', 'min:2', 'max:100'],
            'cliente_telefono' => ['nullable', 'regex:/^57[0-9]{10}$/'],
            'barbero_id' => ['required', 'integer', 'exists:barberos,id'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*' => ['integer', 'exists:servicios,id'],
        ];
    }
}
