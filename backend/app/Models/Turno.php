<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Turno extends Model
{
    use HasFactory;

    // Estados del turno (R4). Se usan constantes para no repetir cadenas sueltas en el código.
    public const PENDIENTE = 'pendiente';
    public const CONFIRMADO = 'confirmado';
    public const ATENDIDO = 'atendido';
    public const CANCELADO = 'cancelado';
    public const NO_ASISTIO = 'no_asistio';

    public const ORIGEN_PANEL = 'panel';
    public const ORIGEN_WHATSAPP = 'whatsapp';

    protected $fillable = [
        'barbero_id',
        'cliente_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'precio_total',
        'estado',
        'origen',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'precio_total' => 'decimal:2',
        ];
    }

    public function barbero(): BelongsTo
    {
        return $this->belongsTo(Barbero::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * N:M con servicios. withPivot expone el precio y la duración que se congelaron
     * al reservar (leer $servicio->pivot->precio, no $servicio->precio).
     */
    public function servicios(): BelongsToMany
    {
        return $this->belongsToMany(Servicio::class, 'turno_servicio')
            ->withPivot('precio', 'duracion_min');
    }
}
