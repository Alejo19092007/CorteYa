<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * `php artisan migrate --seed` (desarrollo): datos base + datos de demo.
     * Orden obligatorio por las llaves foráneas: primero el administrador y los servicios,
     * después barberos y clientes, y al final los turnos, que apuntan a todos ellos.
     */
    public function run(): void
    {
        $this->call([
            ProduccionSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
