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

    /**
     * Horario listo para el formulario: dia_semana => [trabaja, inicio, fin] (solo los días que trabaja).
     *
     * @return array<int, array{trabaja: bool, inicio: string, fin: string}>
     */
    public function jornadas(): array
    {
        return $this->horarios->mapWithKeys(fn ($h) => [$h->dia_semana => [
            'trabaja' => true,
            'inicio' => substr($h->hora_inicio, 0, 5),
            'fin' => substr($h->hora_fin, 0, 5),
        ]])->all();
    }

    /** 1:N: todos los turnos que atiende. */
    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class);
    }
}
