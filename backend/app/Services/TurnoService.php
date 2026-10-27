<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Turno;
use App\Repositories\BarberoRepository;
use App\Repositories\TurnoRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reglas de negocio de los turnos: R1 (sin cruces), R2 (cálculo), R3 (cancelación) y R4 (estados).
 *
 * Lo usan por igual el panel y la API, así cada regla se escribe UNA sola vez.
 * Cuando una regla falla se lanza ReglaNegocioException (422 en la API, alerta en el panel).
 * Las consultas a la base de datos están en los Repositories.
 */
class TurnoService
{
    /** Quién cancela: de eso depende si aplica R3 (decisión 1: solo al cliente). */
    public const CANCELA_ADMIN = 'admin';

    public const CANCELA_CLIENTE = 'cliente';

    /** R3: el cliente puede cancelar hasta 2 horas antes del inicio. */
    private const HORAS_MINIMAS_PARA_CANCELAR = 2;

    /** Cada cuántos minutos se ofrecen horarios al consultar la disponibilidad. */
    private const PASO_DISPONIBILIDAD_MIN = 15;

    /**
     * R4: transiciones de estado permitidas. Desde atendido, cancelado y no_asistio no se sale.
     * (Decisión 4: "atendido" y "no_asistio" además esperan a que pase la hora de inicio.)
     */
    private const TRANSICIONES = [
        Turno::PENDIENTE => [Turno::CONFIRMADO, Turno::CANCELADO],
        Turno::CONFIRMADO => [Turno::ATENDIDO, Turno::NO_ASISTIO, Turno::CANCELADO],
        Turno::ATENDIDO => [],
        Turno::CANCELADO => [],
        Turno::NO_ASISTIO => [],
    ];

    public function __construct(
        private readonly TurnoRepository $turnos,
        private readonly BarberoRepository $barberos,
    ) {}

    /**
     * Crea un turno.
     *
     * @param  array{barbero_id: int, cliente_id: int, fecha: string, hora_inicio: string, servicios: array<int>}  $datos
     *                                                                                                                      La hora de fin y el precio NO se reciben: los calcula R2.
     * @param  string  $origen  Turno::ORIGEN_PANEL o Turno::ORIGEN_WHATSAPP
     */
    public function crear(array $datos, string $origen): Turno
    {
        // Todo en una transacción: o se guarda el turno completo o no se guarda nada.
        return DB::transaction(function () use ($datos, $origen) {
            $plan = $this->planearTurno($datos);

            $turno = $this->turnos->crear([
                'barbero_id' => $plan['barbero_id'],
                'cliente_id' => $datos['cliente_id'],
                'fecha' => $plan['inicio']->toDateString(),
                'hora_inicio' => $plan['inicio']->format('H:i:s'),
                'hora_fin' => $plan['fin']->format('H:i:s'),
                'precio_total' => $plan['precio_total'],
                // Decisión 2: lo creado en el panel nace confirmado; lo de WhatsApp, pendiente
                'estado' => $origen === Turno::ORIGEN_PANEL ? Turno::CONFIRMADO : Turno::PENDIENTE,
                'origen' => $origen,
            ], $plan['lineas']);

            return $turno->load(['barbero.user', 'cliente', 'servicios']);
        });
    }

    /**
     * Edita un turno. Solo se pueden editar los pendientes y confirmados (R4: de atendido,
     * cancelado o no_asistio no se vuelve atrás). Se recalcula R2 y se revalida R1.
     */
    public function actualizar(Turno $turno, array $datos): Turno
    {
        return DB::transaction(function () use ($turno, $datos) {
            $turno = $this->turnos->bloquear($turno->id);

            if (! in_array($turno->estado, [Turno::PENDIENTE, Turno::CONFIRMADO], true)) {
                throw new ReglaNegocioException('Este turno ya no se puede modificar.');
            }

            // Se pasa el turno para que R1 lo ignore (no puede chocar consigo mismo)
            // y para conservar el precio congelado de los servicios que ya tenía.
            $plan = $this->planearTurno($datos, $turno);

            $this->turnos->actualizar($turno, [
                'barbero_id' => $plan['barbero_id'],
                'cliente_id' => $datos['cliente_id'],
                'fecha' => $plan['inicio']->toDateString(),
                'hora_inicio' => $plan['inicio']->format('H:i:s'),
                'hora_fin' => $plan['fin']->format('H:i:s'),
                'precio_total' => $plan['precio_total'],
            ], $plan['lineas']);

            return $turno->load(['barbero.user', 'cliente', 'servicios']);
        });
    }

    /**
     * Confirma, marca atendido o marca no asistió (R4). Para cancelar se usa cancelar().
     */
    public function cambiarEstado(Turno $turno, string $nuevoEstado): Turno
    {
        if ($nuevoEstado === Turno::CANCELADO) {
            throw new InvalidArgumentException('Para cancelar un turno se usa cancelar(), que aplica R3.');
        }

        return DB::transaction(function () use ($turno, $nuevoEstado) {
            $turno = $this->turnos->bloquear($turno->id);

            $this->validarTransicion($turno->estado, $nuevoEstado);

            // Decisión 4: no se puede marcar atendido / no asistió antes de la hora del turno
            if (in_array($nuevoEstado, [Turno::ATENDIDO, Turno::NO_ASISTIO], true)
                && now()->lt($this->inicioDe($turno))) {
                throw new ReglaNegocioException('Aún no ha llegado la hora del turno');
            }

            return $this->turnos->cambiarEstado($turno, $nuevoEstado);
        });
    }

    /**
     * Cancela un turno (cambia el estado a "cancelado"; nunca se borra).
     *
     * R3 (hasta 2 horas antes) aplica SOLO al cliente (API/bot). El administrador puede
     * cancelar en cualquier momento, por ejemplo si un barbero se enferma.
     *
     * @param  string  $quien  CANCELA_ADMIN o CANCELA_CLIENTE
     */
    public function cancelar(Turno $turno, string $quien): Turno
    {
        return DB::transaction(function () use ($turno, $quien) {
            $turno = $this->turnos->bloquear($turno->id);

            $this->validarTransicion($turno->estado, Turno::CANCELADO); // R4

            if ($quien === self::CANCELA_CLIENTE
                && now()->addHours(self::HORAS_MINIMAS_PARA_CANCELAR)->gt($this->inicioDe($turno))) { // R3
                throw new ReglaNegocioException('Ya no es posible cancelar este turno');
            }

            return $this->turnos->cambiarEstado($turno, Turno::CANCELADO);
        });
    }

    /**
     * Horas de inicio libres de un barbero en una fecha para un servicio (o combo) que dura
     * $duracionMin minutos. Lo usan el formulario de nuevo turno y GET /api/barberos/{id}/disponibilidad.
     *
     * @return array<int, string> horas en formato "H:i", ej. ["09:00", "09:15", ...]
     */
    public function disponibilidad(int $barberoId, string $fecha, int $duracionMin): array
    {
        $barbero = $this->barberos->encontrar($barberoId);
        $dia = Carbon::parse($fecha);
        $horario = $this->barberos->horarioDelDia($barberoId, $dia->dayOfWeekIso);

        if (! $barbero || ! $barbero->activo || ! $horario || $duracionMin <= 0) {
            return [];
        }

        $ocupados = $this->turnos->ocupadosDelBarbero($barberoId, $dia->toDateString());
        $cursor = Carbon::parse($dia->toDateString().' '.$horario->hora_inicio);
        $cierre = Carbon::parse($dia->toDateString().' '.$horario->hora_fin);
        $libres = [];

        // Se prueba cada hora de inicio posible mientras el servicio termine antes del cierre
        while ($cursor->copy()->addMinutes($duracionMin)->lte($cierre)) {
            $fin = $cursor->copy()->addMinutes($duracionMin);

            $libre = $ocupados->every(fn (Turno $t) => $fin->format('H:i:s') <= $t->hora_inicio
                || $cursor->format('H:i:s') >= $t->hora_fin);

            // No se ofrecen horas que ya pasaron
            if ($libre && $cursor->gte(now())) {
                $libres[] = $cursor->format('H:i');
            }

            $cursor->addMinutes(self::PASO_DISPONIBILIDAD_MIN);
        }

        return $libres;
    }

    /**
     * Aplica R2 (calcula fin y precio) y R1 (jornada y cruces). Devuelve el plan ya validado.
     * Debe llamarse DENTRO de una transacción: bloquea la fila del barbero.
     *
     * @param  Turno|null  $existente  al editar: se ignora en R1 y se conservan sus precios congelados
     * @return array{barbero_id: int, inicio: Carbon, fin: Carbon, precio_total: float|int, lineas: array}
     */
    private function planearTurno(array $datos, ?Turno $existente = null): array
    {
        // Bloquea al barbero: dos reservas simultáneas del mismo barbero se atienden en fila
        $barbero = $this->barberos->bloquear((int) $datos['barbero_id']);

        if (! $barbero->activo) {
            throw new ReglaNegocioException('El barbero no está activo.');
        }

        // --- R2: duración y precio salen de los servicios elegidos, nunca del formulario ---
        $ids = array_values(array_unique($datos['servicios'] ?? []));
        $servicios = $this->turnos->serviciosActivos($ids);

        if ($servicios->isEmpty()) {
            throw new ReglaNegocioException('Debes elegir al menos un servicio.');
        }
        if ($servicios->count() !== count($ids)) {
            throw new ReglaNegocioException('Uno o más servicios no están disponibles.');
        }

        $lineas = $this->armarLineas($servicios, $existente);
        $duracion = (int) collect($lineas)->sum('duracion_min');
        $precioTotal = collect($lineas)->sum('precio');

        $inicio = Carbon::parse(Carbon::parse($datos['fecha'])->toDateString().' '.$datos['hora_inicio']);
        $fin = $inicio->copy()->addMinutes($duracion);

        // --- R1a: el turno debe caber dentro de la jornada del barbero ese día ---
        $horario = $this->barberos->horarioDelDia($barbero->id, $inicio->dayOfWeekIso);

        if (! $horario) {
            throw new ReglaNegocioException('El barbero no está disponible en ese horario (no trabaja ese día).');
        }

        $caeEnLaJornada = $fin->isSameDay($inicio)
            && $inicio->format('H:i:s') >= $horario->hora_inicio
            && $fin->format('H:i:s') <= $horario->hora_fin;

        if (! $caeEnLaJornada) {
            throw new ReglaNegocioException(sprintf(
                'El barbero no está disponible en ese horario (su jornada ese día es de %s a %s).',
                substr($horario->hora_inicio, 0, 5),
                substr($horario->hora_fin, 0, 5),
            ));
        }

        // --- R1b: no puede solaparse con otro turno del mismo barbero ---
        $choque = $this->turnos->turnoQueSeCruza(
            $barbero->id,
            $inicio->toDateString(),
            $inicio->format('H:i:s'),
            $fin->format('H:i:s'),
            $existente?->id,
        );

        if ($choque) {
            throw new ReglaNegocioException(sprintf(
                'El barbero no está disponible en ese horario (ya tiene un turno de %s a %s).',
                substr($choque->hora_inicio, 0, 5),
                substr($choque->hora_fin, 0, 5),
            ));
        }

        return [
            'barbero_id' => $barbero->id,
            'inicio' => $inicio,
            'fin' => $fin,
            'precio_total' => $precioTotal,
            'lineas' => $lineas,
        ];
    }

    /**
     * Arma las líneas de la tabla pivote: precio y duración CONGELADOS de cada servicio.
     * Al reservar se toma el valor vigente; al editar, un servicio que el turno ya tenía
     * conserva su valor original (cambiar el precio del servicio después no altera el turno).
     *
     * @return array<int, array{precio: mixed, duracion_min: int}>
     */
    private function armarLineas(Collection $servicios, ?Turno $existente): array
    {
        $previos = $existente ? $existente->servicios->keyBy('id') : collect();

        return $servicios->mapWithKeys(function ($servicio) use ($previos) {
            $previo = $previos->get($servicio->id);

            return [$servicio->id => [
                'precio' => $previo ? $previo->pivot->precio : $servicio->precio,
                'duracion_min' => $previo ? $previo->pivot->duracion_min : $servicio->duracion_min,
            ]];
        })->all();
    }

    /** R4: lanza la excepción si el estado actual no permite pasar al nuevo. */
    private function validarTransicion(string $actual, string $nuevo): void
    {
        if (! in_array($nuevo, self::TRANSICIONES[$actual] ?? [], true)) {
            throw new ReglaNegocioException('Cambio de estado no permitido');
        }
    }

    private function inicioDe(Turno $turno): Carbon
    {
        return Carbon::parse($turno->fecha->toDateString().' '.$turno->hora_inicio);
    }
}
