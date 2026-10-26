<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->name('male'),
            // Número de WhatsApp: indicativo 57 + celular de 10 dígitos que empieza en 3. Único.
            'telefono' => '57'.fake()->unique()->numerify('3#########'),
            // Se recalcula al final del seeder a partir de los turnos atendidos reales
            'turnos_atendidos' => 0,
        ];
    }
}
