<?php

namespace App\Repositories;

use App\Models\Barbero;
use App\Models\Horario;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Consultas sobre barberos y sus horarios. Aquí no hay reglas de negocio: solo Eloquent.
 */
class BarberoRepository
{
    /**
     * Trae al barbero BLOQUEANDO su fila hasta que termine la transacción.
     * Dos reservas simultáneas del mismo barbero (panel y bot) quedan en fila: la segunda
     * espera y ya ve el turno de la primera, así R1 no se puede saltar por una carrera.
     */
    public function bloquear(int $barberoId): Barbero
    {
        return Barbero::lockForUpdate()->findOrFail($barberoId);
    }

    /** Jornada del barbero en un día de la semana (1 = lunes ... 7 = domingo), o null si no trabaja. */
    public function horarioDelDia(int $barberoId, int $diaSemana): ?Horario
    {
        return Horario::where('barbero_id', $barberoId)
            ->where('dia_semana', $diaSemana)
            ->first();
    }

    public function encontrar(int $barberoId): ?Barbero
    {
        return Barbero::find($barberoId);
    }

    /**
     * Listado del panel con búsqueda, filtro y paginación.
     * Filtros (opcionales): q (nombre, correo o teléfono) y activo ('1' | '0').
     * withCount('turnos') trae cuántos turnos tiene cada barbero en la misma consulta.
     */
    public function buscar(array $filtros, int $porPagina = 10): LengthAwarePaginator
    {
        return Barbero::query()
            ->with('user')
            ->withCount('turnos')
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->where(fn ($q) => $q
                ->where('telefono', 'like', "%{$texto}%")
                ->orWhereHas('user', fn ($u) => $u
                    ->where('name', 'like', "%{$texto}%")
                    ->orWhere('email', 'like', "%{$texto}%"))))
            ->when(isset($filtros['activo']) && $filtros['activo'] !== '', fn ($q) => $q->where('activo', (bool) $filtros['activo']))
            ->orderBy('id')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /** Crea la cuenta de acceso (rol barbero) y el perfil del barbero (relación 1:1). */
    public function crear(array $usuario, array $barbero): Barbero
    {
        $user = User::create($usuario + ['rol' => User::ROL_BARBERO]);

        return $user->barbero()->create($barbero)->setRelation('user', $user);
    }

    /** Actualiza el perfil y la cuenta. La contraseña solo se cambia si viene informada. */
    public function actualizar(Barbero $barbero, array $usuario, array $datosBarbero): Barbero
    {
        $barbero->user->update(array_filter($usuario, fn ($valor, $campo) => $campo !== 'password' || filled($valor), ARRAY_FILTER_USE_BOTH));
        $barbero->update($datosBarbero);

        return $barbero;
    }

    /**
     * Reemplaza los horarios del barbero por los recibidos.
     *
     * @param  array<int, array{hora_inicio: string, hora_fin: string}>  $horarios  dia_semana => jornada
     */
    public function reemplazarHorarios(Barbero $barbero, array $horarios): void
    {
        $barbero->horarios()->delete();

        foreach ($horarios as $dia => $jornada) {
            $barbero->horarios()->create([
                'dia_semana' => $dia,
                'hora_inicio' => $this->conSegundos($jornada['hora_inicio']),
                'hora_fin' => $this->conSegundos($jornada['hora_fin']),
            ]);
        }
    }

    /** El formulario envía "09:00"; se guarda "09:00:00", igual que el resto de horas del proyecto. */
    private function conSegundos(string $hora): string
    {
        return strlen($hora) === 5 ? $hora.':00' : $hora;
    }

    /** Turnos pendientes o confirmados desde hoy: los que un cambio de horario podría dejar sin cobertura. */
    public function turnosFuturosActivos(int $barberoId): Collection
    {
        return Turno::where('barbero_id', $barberoId)
            ->whereDate('fecha', '>=', today())
            ->whereIn('estado', [Turno::PENDIENTE, Turno::CONFIRMADO])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();
    }

    /**
     * Ingresos de cada barbero entre dos fechas (suma de sus turnos atendidos), del que más al que menos.
     * Incluye a los que no facturaron (ingresos = 0): así el gráfico muestra a todo el equipo.
     * withSum hace el cálculo en la base de datos, en una sola consulta.
     *
     * @return Collection<int, Barbero> cada uno con el atributo "ingresos"
     */
    public function ingresosPorBarbero(string $desde, string $hasta): Collection
    {
        return Barbero::query()
            ->with('user')
            ->withSum(['turnos as ingresos' => fn ($q) => $q
                ->where('estado', Turno::ATENDIDO)
                ->whereDate('fecha', '>=', $desde)
                ->whereDate('fecha', '<=', $hasta)], 'precio_total')
            ->get()
            ->each(fn (Barbero $b) => $b->ingresos = (float) $b->ingresos)
            ->sortByDesc('ingresos')
            ->values();
    }

    public function tieneTurnos(Barbero $barbero): bool
    {
        return $barbero->turnos()->exists();
    }

    /** Borra al barbero y su cuenta (los horarios caen en cascada por la llave foránea). */
    public function eliminar(Barbero $barbero): void
    {
        $user = $barbero->user;
        $barbero->delete();
        $user->delete();
    }
}
