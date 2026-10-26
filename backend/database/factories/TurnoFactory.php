<?php

namespace Database\Factories;

use App\Models\Barbero;
use App\Models\Cliente;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

/**
 * @extends Factory<Turno>
 */
class TurnoFactory extends Factory
{
    /**
     * Valores mínimos para poder crear un turno suelto en pruebas.
     * Para datos coherentes (con servicios) se usa el método para().
     */
    public function definition(): array
    {
        return [
            'barbero_id' => Barbero::factory(),
            'cliente_id' => Cliente::factory(),
            'fecha' => today()->toDateString(),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:45:00',
            'precio_total' => 25000,
            'estado' => Turno::PENDIENTE,
            'origen' => Turno::ORIGEN_PANEL,
        ];
    }

    /**
     * Arma un turno COHERENTE a partir de su inicio y sus servicios:
     *  - R2: hora_fin = inicio + suma de duraciones; precio_total = suma de precios.
     *  - Congela precio y duración de cada servicio en la pivote turno_servicio.
     *
     * Uso: Turno::factory()->para($barbero, $cliente, $inicio, $servicios)->estado(...)->create()
     *
     * @param  Collection<int, \App\Models\Servicio>  $servicios
     */
    public function para(Barbero $barbero, Cliente $cliente, Carbon $inicio, Collection $servicios): static
    {
        $fin = $inicio->copy()->addMinutes((int) $servicios->sum('duracion_min'));

        return $this
            ->state([
                'barbero_id' => $barbero->id,
                'cliente_id' => $cliente->id,
                'fecha' => $inicio->toDateString(),
                'hora_inicio' => $inicio->format('H:i:s'),
                'hora_fin' => $fin->format('H:i:s'),
                'precio_total' => $servicios->sum('precio'),
            ])
            ->afterCreating(function (Turno $turno) use ($servicios) {
                $turno->servicios()->attach(
                    $servicios->mapWithKeys(fn ($s) => [
                        $s->id => ['precio' => $s->precio, 'duracion_min' => $s->duracion_min],
                    ])->all()
                );
            });
    }

    public function estado(string $estado): static
    {
        return $this->state(['estado' => $estado]);
    }

    public function origen(string $origen): static
    {
        return $this->state(['origen' => $origen]);
    }
}
