<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Formato de las credenciales de POST /api/login. Comprobar que sean correctas lo hace AuthController.
 */
class ApiLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el login es público
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            // Nombre del token (p. ej. "bot-whatsapp"): un nuevo login con el mismo nombre reemplaza al anterior
            'device_name' => ['nullable', 'string', 'max:60'],
        ];
    }
}
