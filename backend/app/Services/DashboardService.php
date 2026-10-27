<?php

namespace App\Services;

use App\Repositories\BarberoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\TurnoRepository;
use Carbon\Carbon;

/**
 * Calcula lo que muestra el dashboard del administrador: 4 tarjetas con su variación,
 * el gráfico de ingresos por barbero y los turnos de hoy.
 *
 * Las comparaciones "contra el mes anterior" usan el MISMO PERIODO: del día 1 al día de hoy
 * de este mes contra del día 1 al mismo día del mes pasado. Comparar medio mes con un mes
 * completo mostraría siempre una caída que no es real.
 */
class DashboardService
{
    public function __construct(
        private readonly TurnoRepository $turnos,
        private readonly BarberoRepository $barberos,
        private readonly ClienteRepository $clientes,
    ) {}

    /**
     * @param  Carbon|null  $ahora  momento de referencia (por defecto, ahora); las pruebas pasan una fecha fija
     */
    public function resumen(?Carbon $ahora = null): array
    {
        $hoy = ($ahora ?? now())->copy();
        $ayer = $hoy->copy()->subDay();

        // Mes actual: del día 1 a hoy. Mes anterior: del día 1 al mismo día (o al último día si es más corto).
        $inicioMes = $hoy->copy()->startOfMonth();
        $inicioMesPrevio = $hoy->copy()->subMonthNoOverflow()->startOfMonth();
        $finPeriodoPrevio = $inicioMesPrevio->copy()->addDays(min($hoy->day, $inicioMesPrevio->daysInMonth) - 1);

        $mes = [$inicioMes->toDateString(), $hoy->toDateString()];
        $mesPrevio = [$inicioMesPrevio->toDateString(), $finPeriodoPrevio->toDateString()];

        $ingresosPorBarbero = $this->barberos->ingresosPorBarbero(...$mes);

        return [
            'fecha' => $hoy,
            'mes' => $hoy->copy()->locale('es')->isoFormat('MMMM [de] YYYY'),
            'tarjetas' => [
                'turnosHoy' => [
                    'valor' => $actual = $this->turnos->contarDelDia($hoy->toDateString()),
                    'variacion' => $this->variacion($actual, $this->turnos->contarDelDia($ayer->toDateString()), porcentual: false, subirEsBueno: true),
                    'comparado' => 'vs. ayer',
                ],
                'ingresosMes' => [
                    'valor' => $actual = $this->turnos->ingresosEntre(...$mes),
                    'variacion' => $this->variacion($actual, $this->turnos->ingresosEntre(...$mesPrevio), porcentual: true, subirEsBueno: true),
                    'comparado' => 'vs. mes anterior',
                ],
                'clientesNuevos' => [
                    'valor' => $actual = $this->clientes->contarNuevos(...$mes),
                    'variacion' => $this->variacion($actual, $this->clientes->contarNuevos(...$mesPrevio), porcentual: false, subirEsBueno: true),
                    'comparado' => 'vs. mes anterior',
                ],
                'cancelaciones' => [
                    'valor' => $actual = $this->turnos->cancelacionesEntre(...$mes),
                    // Aquí BAJAR es bueno: menos cancelaciones es una buena noticia (se pinta en verde)
                    'variacion' => $this->variacion($actual, $this->turnos->cancelacionesEntre(...$mesPrevio), porcentual: true, subirEsBueno: false),
                    'comparado' => 'vs. mes anterior',
                ],
            ],
            'grafico' => [
                'etiquetas' => $ingresosPorBarbero->map(fn ($b) => $b->user->name)->all(),
                'valores' => $ingresosPorBarbero->map(fn ($b) => $b->ingresos)->all(),
            ],
            'turnosDeHoy' => $this->turnos->delDia($hoy->toDateString()),
        ];
    }

    /**
     * Compara el valor actual contra el anterior.
     *
     * @param  bool  $porcentual  true: diferencia en % (dinero, cancelaciones); false: diferencia absoluta (conteos pequeños)
     * @param  bool  $subirEsBueno  define de qué color se pinta el cambio: más ingresos es bueno, más cancelaciones es malo
     * @return array{direccion: string, valor: int|null, porcentual: bool, bueno: bool|null}
     *                                                                                        direccion: 'sube' | 'baja' | 'igual' | 'nuevo' (antes era 0 y ahora no: no se puede sacar un %)
     */
    private function variacion(float|int $actual, float|int $anterior, bool $porcentual, bool $subirEsBueno): array
    {
        $diferencia = $actual - $anterior;

        if ($diferencia == 0) {
            return ['direccion' => 'igual', 'valor' => 0, 'porcentual' => $porcentual, 'bueno' => null];
        }

        $sube = $diferencia > 0;

        // Si antes era 0, un porcentaje sería dividir por cero: se dice "nuevo" en vez de inventar un número
        if ($porcentual && $anterior == 0) {
            return ['direccion' => 'nuevo', 'valor' => null, 'porcentual' => true, 'bueno' => $subirEsBueno];
        }

        return [
            'direccion' => $sube ? 'sube' : 'baja',
            'valor' => (int) round($porcentual ? abs($diferencia) / $anterior * 100 : abs($diferencia)),
            'porcentual' => $porcentual,
            'bueno' => $sube === $subirEsBueno,
        ];
    }
}
