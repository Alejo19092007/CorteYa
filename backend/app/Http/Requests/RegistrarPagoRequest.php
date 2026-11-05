<?php

namespace App\Http\Requests;

use App\Exceptions\ReglaNegocioException;
use App\Models\Turno;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Método con el que pagó el cliente. Solo valida el formato: que el turno esté atendido y no pagado
 * lo decide TurnoService (R6). No se pide el monto: es el precio_total del turno.
 */
class RegistrarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la política TurnoPolicy@registrarPago se aplica en la ruta
    }

    public function rules(): array
    {
        return [
            'metodo_pago' => ['required', Rule::in(array_keys(Turno::METODOS_PAGO))],
        ];
    }

    /** El formulario es un modal sin lugar para errores por campo: se muestra como alerta, igual que las reglas de negocio. */
    protected function failedValidation(Validator $validator): void
    {
        throw new ReglaNegocioException($validator->errors()->first());
    }

    public function messages(): array
    {
        return [
            'metodo_pago.required' => 'Elige cómo pagó el cliente.',
            'metodo_pago.in' => 'Elige un método de pago válido.',
        ];
    }
}
