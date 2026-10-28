<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Barbero visible para el bot: solo lo necesario para ofrecerlo al cliente (sin correo ni cuenta).
 *
 * @mixin \App\Models\Barbero
 */
class BarberoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->user->name,
        ];
    }
}
