<?php

namespace Database\Seeders;

use App\Models\Barbero;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class TurnoHistorialSeeder extends Seeder
{
    private Collection $barberos;

    private Collection $clientes;

    private Collection $servicios;

    /**
     * Turnos de los últimos 60 días (para que el dashboard tenga datos), de los 3 días
     * siguientes (para que las agendas no estén vacías) y los 9 turnos atendidos del
     * cliente fiel (R5).
     *
     * Cumple R1 por construcción: para cada barbero y día los turnos se colocan uno detrás
     * de otro, así que nunca se solapan, y siempre caen entre 9:00 y 18:00 (dentro de 6–22).
     * Cumple R2 porque TurnoFactory::para() calcula hora_fin y precio_total.
     */
    public function run(): void
    {
        $this->barberos = Barbero::orderBy('id')->get();
        $this->servicios = Servicio::all();
        // El cliente fiel se excluye del azar: debe tener exactamente 9 turnos atendidos.
        $this->clientes = Cliente::where('telefono', '!=', ClienteSeeder::TELEFONO_FIEL)->get();

        // Pasado: de hace 60 días hasta ayer. Dos meses de historial para que el dashboard pueda
        // comparar "este mes" contra "el mes anterior" con datos reales en ambos lados.
        for ($dias = 60; $dias >= 1; $dias--) {
            foreach ($this->barberos as $barbero) {
                $this->agendarDia($barbero, today()->subDays($dias), pasado: true);
            }
        }

        // Futuro: mañana y los dos días siguientes
        for ($dias = 1; $dias <= 3; $dias++) {
            foreach ($this->barberos as $barbero) {
                $this->agendarDia($barbero, today()->addDays($dias), pasado: false);
            }
        }

        $this->turnosDelClienteFiel();
    }

    /** Coloca entre 3 y 6 turnos seguidos para un barbero en un día. */
    private function agendarDia(Barbero $barbero, Carbon $dia, bool $pasado): void
    {
        $inicio = $dia->copy()->setTime(9, 0);
        $limite = $dia->copy()->setTime(18, 0);

        for ($i = random_int(3, 6); $i > 0; $i--) {
            $servicios = $this->elegirServicios();
            $fin = $inicio->copy()->addMinutes((int) $servicios->sum('duracion_min'));

            if ($fin->gt($limite)) {
                break; // ya no cabe otro turno ese día
            }

            [$estado, $origen] = $pasado ? $this->estadoPasado() : $this->estadoFuturo();

            Turno::factory()
                ->para($barbero, $this->clientes->random(), $inicio->copy(), $servicios)
                ->estado($estado)
                ->origen($origen)
                ->create();

            // Siguiente turno: justo después, o con 15–30 minutos de pausa
            $inicio = $fin->copy()->addMinutes([0, 15, 30][array_rand([0, 15, 30])]);
        }
    }

    /** 70 % de las veces un servicio, 30 % dos servicios. */
    private function elegirServicios(): Collection
    {
        return $this->servicios->random(random_int(1, 10) <= 7 ? 1 : 2)->values();
    }

    /** Turnos pasados: la mayoría atendidos; algunos cancelados o con inasistencia. */
    private function estadoPasado(): array
    {
        $r = random_int(1, 100);
        $estado = match (true) {
            $r <= 70 => Turno::ATENDIDO,
            $r <= 85 => Turno::CANCELADO,
            default => Turno::NO_ASISTIO,
        };

        return [$estado, $this->origenAleatorio()];
    }

    /** Turnos futuros: los de WhatsApp pueden estar pendientes; los del panel nacen confirmados. */
    private function estadoFuturo(): array
    {
        $origen = $this->origenAleatorio();
        $estado = ($origen === Turno::ORIGEN_WHATSAPP && random_int(0, 1) === 0)
            ? Turno::PENDIENTE
            : Turno::CONFIRMADO;

        return [$estado, $origen];
    }

    private function origenAleatorio(): string
    {
        return random_int(1, 100) <= 55 ? Turno::ORIGEN_WHATSAPP : Turno::ORIGEN_PANEL;
    }

    /**
     * 9 turnos atendidos del cliente fiel, a las 20:00 (después de la franja del azar,
     * así no se cruzan con nada). Su siguiente turno sale gratis (R5).
     */
    private function turnosDelClienteFiel(): void
    {
        $fiel = Cliente::where('telefono', ClienteSeeder::TELEFONO_FIEL)->firstOrFail();
        $barbero = $this->barberos->first();
        $corte = $this->servicios->where('nombre', 'Corte clásico')->values();

        for ($dias = 1; $dias <= 9; $dias++) {
            Turno::factory()
                ->para($barbero, $fiel, today()->subDays($dias)->setTime(20, 0), $corte)
                ->estado(Turno::ATENDIDO)
                ->origen(Turno::ORIGEN_WHATSAPP)
                ->create();
        }
    }
}
