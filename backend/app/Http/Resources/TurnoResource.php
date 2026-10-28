<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cómo se ve un turno en la API. Los servicios muestran el precio y la duración CONGELADOS
 * al reservar (tabla pivote), no los valores actuales del servicio.
 *
 * @mixin \App\Models\Turno
 */
class TurnoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha->toDateString(),
            'hora_inicio' => substr($this->hora_inicio, 0, 5),
            'hora_fin' => substr($this->hora_fin, 0, 5),
            'precio_total' => (float) $this->precio_total,
            'estado' => $this->estado,
            'origen' => $this->origen,
            'barbero' => [
                'id' => $this->barbero->id,
                'nombre' => $this->barbero->user->name,
            ],
            'cliente' => [
                'id' => $this->cliente->id,
                'nombre' => $this->cliente->nombre,
                'telefono' => $this->cliente->telefono,
            ],
            'servicios' => $this->servicios->map(fn ($servicio) => [
                'id' => $servicio->id,
                'nombre' => $servicio->nombre,
                'precio' => (float) $servicio->pivot->precio,
                'duracion_min' => (int) $servicio->pivot->duracion_min,
            ])->values(),
        ];
    }
}
