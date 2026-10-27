<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Barbero;
use App\Repositories\BarberoRepository;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de los barberos: cuenta de acceso + perfil + horarios, siempre juntos.
 */
class BarberoService
{
    public function __construct(private readonly BarberoRepository $barberos) {}

    /**
     * Crea la cuenta (rol barbero), el perfil y sus horarios. En una transacción: si algo falla
     * no queda un usuario suelto sin perfil.
     *
     * @param  array{name: string, email: string, password: string, telefono: string, activo: bool, horarios: array}  $datos
     */
    public function crear(array $datos): Barbero
    {
        return DB::transaction(function () use ($datos) {
            $barbero = $this->barberos->crear(
                ['name' => $datos['name'], 'email' => $datos['email'], 'password' => $datos['password']],
                ['telefono' => $datos['telefono'], 'activo' => $datos['activo']],
            );

            $this->barberos->reemplazarHorarios($barbero, $datos['horarios']);

            return $barbero;
        });
    }

    public function actualizar(Barbero $barbero, array $datos): Barbero
    {
        return DB::transaction(function () use ($barbero, $datos) {
            // Antes de tocar nada: el nuevo horario no puede dejar fuera turnos ya reservados
            $this->validarQueNoDejaTurnosFuera($barbero, $datos['horarios']);

            $this->barberos->actualizar(
                $barbero,
                ['name' => $datos['name'], 'email' => $datos['email'], 'password' => $datos['password'] ?? null],
                ['telefono' => $datos['telefono'], 'activo' => $datos['activo']],
            );

            $this->barberos->reemplazarHorarios($barbero, $datos['horarios']);

            return $barbero->fresh(['user', 'horarios']);
        });
    }

    /** Activa o desactiva al barbero. Uno inactivo no recibe turnos nuevos, pero conserva su historial. */
    public function alternarActivo(Barbero $barbero): Barbero
    {
        $barbero->update(['activo' => ! $barbero->activo]);

        return $barbero;
    }

    /**
     * Un barbero con turnos registrados NO se borra (se perdería el historial y la base de datos lo
     * impide): hay que desactivarlo. Solo se elimina si nunca tuvo turnos.
     */
    public function eliminar(Barbero $barbero): void
    {
        if ($this->barberos->tieneTurnos($barbero)) {
            throw new ReglaNegocioException(
                'Este barbero tiene turnos registrados y no se puede eliminar. Desactívalo para que deje de recibir turnos.'
            );
        }

        DB::transaction(fn () => $this->barberos->eliminar($barbero));
    }

    /**
     * Regla: no se puede cambiar el horario de un barbero si algún turno futuro (pendiente o
     * confirmado) quedaría fuera de su nueva jornada. Protege a R1: no deja turnos "huérfanos".
     *
     * @param  array<int, array{hora_inicio: string, hora_fin: string}>  $horarios  dia_semana => jornada
     */
    private function validarQueNoDejaTurnosFuera(Barbero $barbero, array $horarios): void
    {
        foreach ($this->barberos->turnosFuturosActivos($barbero->id) as $turno) {
            if ($turno->inicio()->lt(now())) {
                continue; // ya empezó: el horario nuevo no lo afecta
            }

            $jornada = $horarios[$turno->fecha->dayOfWeekIso] ?? null;

            $dentro = $jornada
                && substr($turno->hora_inicio, 0, 5) >= $jornada['hora_inicio']
                && substr($turno->hora_fin, 0, 5) <= $jornada['hora_fin'];

            if (! $dentro) {
                throw new ReglaNegocioException(sprintf(
                    'No se puede cambiar el horario: hay un turno el %s de %s a %s que quedaría fuera de la jornada. Cancela o mueve ese turno primero.',
                    $turno->fecha->format('d/m/Y'),
                    substr($turno->hora_inicio, 0, 5),
                    substr($turno->hora_fin, 0, 5),
                ));
            }
        }
    }
}
