<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barbero extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'telefono',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /** 1:1 inversa: la cuenta con la que el barbero inicia sesión. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 1:N: un horario por cada día de la semana que trabaja. */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }

    /** 1:N: todos los turnos que atiende. */
    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class);
    }
}
