<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'telefono',
        'turnos_atendidos',
    ];

    protected function casts(): array
    {
        return [
            'turnos_atendidos' => 'integer',
        ];
    }

    /** 1:N: historial de turnos del cliente. */
    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class);
    }
}
