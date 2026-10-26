<?php

namespace Database\Factories;

use App\Models\Barbero;
use App\Models\Horario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Horario>
 */
class HorarioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'barbero_id' => Barbero::factory(),
            'dia_semana' => fake()->numberBetween(1, 7),
            // Jornada completa por defecto; los seeders la fijan a 6:00–22:00 los 7 días
            'hora_inicio' => '06:00:00',
            'hora_fin' => '22:00:00',
        ];
    }
}
