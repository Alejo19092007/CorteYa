<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida un servicio al crearlo o editarlo (UpdateServicioRequest hereda de esta clase).
 */
class StoreServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso ya lo controla el middleware "role:admin" de la ruta
    }

    /** El interruptor "activo" llega como "1" o no llega: se convierte en booleano antes de validar. */
    protected function prepareForValidation(): void
    {
        $this->merge(['activo' => $this->boolean('activo')]);
    }

    public function rules(): array
    {
        return [
            // Al editar, el nombre puede repetirse consigo mismo (se ignora el propio servicio)
            'nombre' => ['required', 'string', 'max:80', Rule::unique('servicios', 'nombre')->ignore($this->route('servicio'))],
            'duracion_min' => ['required', 'integer', 'min:1', 'max:480'],
            'precio' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'activo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un servicio con ese nombre.',
        ];
    }

    /** @return array{nombre: string, duracion_min: int, precio: mixed, activo: bool} */
    public function datos(): array
    {
        $datos = $this->validated();

        return [
            'nombre' => $datos['nombre'],
            'duracion_min' => (int) $datos['duracion_min'],
            'precio' => $datos['precio'],
            'activo' => (bool) $datos['activo'],
        ];
    }
}
