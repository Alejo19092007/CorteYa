<?php

namespace Database\Factories;

use App\Models\Barbero;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barbero>
 */
class BarberoFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Cada barbero necesita su cuenta de usuario (relación 1:1)
            'user_id' => User::factory()->barbero(),
            // Celular colombiano con indicativo 57
            'telefono' => '57'.fake()->unique()->numerify('3#########'),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }
}
