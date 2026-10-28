<?php

namespace App\Http\Requests;

/**
 * Servicio recibido por la API (JSON). Igual que en el panel, pero "activo" es opcional:
 * al crear queda activo por defecto y al editar, si no viene, conserva el valor que tenía.
 */
class StoreServicioApiRequest extends StoreServicioRequest
{
    /** En JSON no hay casillas: no se inventa un "activo = false" cuando el campo no viene. */
    protected function prepareForValidation(): void {}

    public function datos(): array
    {
        $validados = $this->validated();

        $datos = [
            'nombre' => $validados['nombre'],
            'duracion_min' => (int) $validados['duracion_min'],
            'precio' => $validados['precio'],
        ];

        if (array_key_exists('activo', $validados)) {
            $datos['activo'] = (bool) $validados['activo'];
        } elseif ($this->isMethod('POST')) {
            $datos['activo'] = true;
        }

        return $datos;
    }
}
