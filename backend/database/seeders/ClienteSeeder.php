<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    /** Teléfono del cliente fiel: lo usan el historial (9 turnos atendidos) y la demo de R5. */
    public const TELEFONO_FIEL = '573001234567';

    public function run(): void
    {
        // Clientes con datos fijos: los usan los turnos de demo y la demostración de R5.
        $fijos = [
            ['Juan García', '573001110001'],
            ['Carlos López', '573001110002'],
            ['Sebastián Rojas', '573001110003'],
            ['Mateo Torres', '573001110004'],
            ['Daniela Silva', self::TELEFONO_FIEL], // cliente fiel (9 turnos atendidos)
        ];

        // Fechas de alta repartidas en los últimos 60 días: así la tarjeta "Clientes nuevos" del
        // dashboard tiene qué comparar contra el mes anterior (si todos nacieran hoy, saldrían 25 nuevos).
        foreach ($fijos as [$nombre, $telefono]) {
            Cliente::factory()->create([
                'nombre' => $nombre,
                'telefono' => $telefono,
                'created_at' => now()->subDays(55),
            ]);
        }

        // Resto de clientes aleatorios para que las listas y el dashboard tengan volumen.
        // El estado es una función: así cada cliente recibe SU propia fecha (con un valor fijo serían todos iguales)
        Cliente::factory()->count(20)
            ->state(fn () => ['created_at' => fake()->dateTimeBetween('-60 days', 'now')])
            ->create();
    }
}
