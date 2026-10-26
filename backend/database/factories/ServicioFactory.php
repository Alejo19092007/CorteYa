<?php

namespace Database\Factories;

use App\Models\Servicio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Servicio>
 */
class ServicioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(2, true),
            'duracion_min' => fake()->randomElement([15, 30, 45, 60]),
            // Precios en pesos colombianos, múltiplos de 5.000
            'precio' => fake()->numberBetween(3, 12) * 5000,
            'activo' => true,
        ];
    }
}
