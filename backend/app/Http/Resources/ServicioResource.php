<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cómo se ve un servicio en la API. Aquí se decide qué campos salen y con qué tipo.
 *
 * @mixin \App\Models\Servicio
 */
class ServicioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'duracion_min' => $this->duracion_min,
            'precio' => (float) $this->precio,
            'activo' => (bool) $this->activo,
        ];
    }
}
