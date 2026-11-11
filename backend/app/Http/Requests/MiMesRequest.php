<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Mes que consulta el barbero en "Mi mes": formato AAAA-MM y nunca uno futuro (no hay nada que mostrar).
 * Si viene mal (alguien editó la URL), se vuelve al mes actual con un aviso en vez de mostrar un error técnico.
 */
class MiMesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el middleware role:barbero ya filtró la ruta
    }

    public function rules(): array
    {
        return [
            'mes' => ['nullable', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('mi-mes')->with('error', 'Ese mes no es válido. Te mostramos el mes actual.')
        );
    }
}
