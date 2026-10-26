<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Horario extends Model
{
    use HasFactory;

    protected $fillable = [
        'barbero_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }

    public function barbero(): BelongsTo
    {
        return $this->belongsTo(Barbero::class);
    }
}
