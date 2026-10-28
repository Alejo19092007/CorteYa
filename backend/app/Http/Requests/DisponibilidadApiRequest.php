<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Parámetros de GET /api/barberos/{id}/disponibilidad. Los servicios hacen falta porque
 * de ellos sale cuánto dura el turno (R2) y, con eso, qué horas caben.
 */
class DisponibilidadApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d'],
            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*' => ['integer'],
        ];
    }
}
