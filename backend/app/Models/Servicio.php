<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Servicio extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'duracion_min',
        'precio',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'duracion_min' => 'integer',
            'precio' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * N:M inversa. Se indica el nombre de la pivote ('turno_servicio') porque
     * Laravel supondría 'servicio_turno' (orden alfabético de los dos modelos).
     */
    public function turnos(): BelongsToMany
    {
        return $this->belongsToMany(Turno::class, 'turno_servicio')
            ->withPivot('precio', 'duracion_min');
    }
}
