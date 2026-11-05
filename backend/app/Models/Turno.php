<?php

namespace App\Models;

use Carbon\Carbon;
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
    public const ORIGEN_TELEGRAM = 'telegram';
    public const ORIGEN_WHATSAPP = 'whatsapp';

    /** Canales por los que el bot puede crear turnos (el panel no cuenta: lo crea el personal). */
    public const ORIGENES_DEL_BOT = [self::ORIGEN_TELEGRAM, self::ORIGEN_WHATSAPP];

    /** Cómo pudo pagar el cliente (R6: el pago se registra a mano; el sistema no cobra en línea). */
    public const METODOS_PAGO = [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'nequi' => 'Nequi',
        'daviplata' => 'Daviplata',
        'tarjeta' => 'Tarjeta',
    ];

    protected $fillable = [
        'barbero_id',
        'cliente_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'precio_total',
        'estado',
        'origen',
        'pagado_en',
        'metodo_pago',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'precio_total' => 'decimal:2',
            'pagado_en' => 'datetime',
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

    /** Momento exacto en que empieza el turno (fecha + hora de inicio). */
    public function inicio(): Carbon
    {
        return Carbon::parse($this->fecha->toDateString().' '.$this->hora_inicio);
    }

    public function fin(): Carbon
    {
        return Carbon::parse($this->fecha->toDateString().' '.$this->hora_fin);
    }

    /** "9:00 a. m." — para mostrar en pantalla. */
    public function horaInicioLegible(): string
    {
        return $this->inicio()->locale('es')->isoFormat('h:mm a');
    }

    public function horaFinLegible(): string
    {
        return $this->fin()->locale('es')->isoFormat('h:mm a');
    }

    /** "Corte clásico + Barba" */
    public function serviciosTexto(): string
    {
        return $this->servicios->pluck('nombre')->implode(' + ');
    }

    public function duracionMin(): int
    {
        return (int) $this->servicios->sum('pivot.duracion_min');
    }

    /** ¿El cliente ya pagó? (R6) */
    public function estaPagado(): bool
    {
        return $this->pagado_en !== null;
    }

    /** Atendido y todavía sin pago registrado: dinero "por cobrar". */
    public function estaPorCobrar(): bool
    {
        return $this->estado === self::ATENDIDO && ! $this->estaPagado();
    }

    /** "Efectivo", "Nequi"... o null si no ha pagado. */
    public function metodoPagoLegible(): ?string
    {
        return $this->metodo_pago ? (self::METODOS_PAGO[$this->metodo_pago] ?? $this->metodo_pago) : null;
    }

    /** Solo los pendientes y confirmados se pueden editar o cancelar (R4). */
    public function esEditable(): bool
    {
        return in_array($this->estado, [self::PENDIENTE, self::CONFIRMADO], true);
    }
}
