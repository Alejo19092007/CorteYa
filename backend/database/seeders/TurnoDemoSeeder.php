<?php

namespace Database\Seeders;

use App\Models\Barbero;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\Turno;
use Illuminate\Database\Seeder;

class TurnoDemoSeeder extends Seeder
{
    /**
     * Turnos de HOY para la sustentación, calculados relativos a la hora actual (no fijos),
     * de modo que siempre haya uno por cada situación a demostrar:
     *
     *  -2h confirmado  → marcar "atendido"
     *  -1h confirmado  → marcar "no asistió"
     *  +1h pendiente   → confirmar; y cancelarlo como cliente da 422 (faltan menos de 2 h, R3)
     *  +3h confirmado  → "Atendido" bloqueado (aún no llega la hora); cancelable como cliente (R3)
     *
     * Cada turno va con un barbero/cliente distinto y un servicio corto, para que no se
     * crucen entre sí (R1).
     */
    public function run(): void
    {
        $barberos = Barbero::orderBy('id')->get();

        $demo = [
            ['horas' => -2, 'telefono' => '573001110001', 'barbero' => 0, 'servicio' => 'Corte clásico', 'estado' => Turno::CONFIRMADO, 'origen' => Turno::ORIGEN_PANEL],
            ['horas' => -1, 'telefono' => '573001110002', 'barbero' => 1, 'servicio' => 'Barba', 'estado' => Turno::CONFIRMADO, 'origen' => Turno::ORIGEN_WHATSAPP],
            ['horas' => 1, 'telefono' => '573001110003', 'barbero' => 2, 'servicio' => 'Corte clásico', 'estado' => Turno::PENDIENTE, 'origen' => Turno::ORIGEN_WHATSAPP],
            ['horas' => 3, 'telefono' => '573001110004', 'barbero' => 0, 'servicio' => 'Corte clásico', 'estado' => Turno::CONFIRMADO, 'origen' => Turno::ORIGEN_PANEL],
        ];

        foreach ($demo as $d) {
            $servicio = Servicio::where('nombre', $d['servicio'])->firstOrFail();

            // Hora actual ± N horas, redondeada hacia abajo a múltiplos de 5 minutos
            $base = now()->addHours($d['horas']);
            $inicio = $base->copy()->setTime($base->hour, intdiv($base->minute, 5) * 5);
            $fin = $inicio->copy()->addMinutes($servicio->duracion_min);

            // Cerca de la medianoche el turno caería en otro día o fuera de 6:00–22:00:
            // se omite en vez de romper R1.
            if (! $inicio->isSameDay(today())
                || $inicio->lt(today()->setTime(6, 0))
                || $fin->gt(today()->setTime(22, 0))) {
                $this->command?->warn("Turno de demo ({$d['horas']}h) omitido: queda fuera del día o del horario 6:00–22:00.");

                continue;
            }

            Turno::factory()
                ->para(
                    $barberos[$d['barbero']],
                    Cliente::where('telefono', $d['telefono'])->firstOrFail(),
                    $inicio,
                    collect([$servicio]),
                )
                ->estado($d['estado'])
                ->origen($d['origen'])
                ->create();
        }
    }
}
