<?php

namespace Database\Seeders;

use App\Models\Servicio;
use Illuminate\Database\Seeder;

class ServicioSeeder extends Seeder
{
    /** Los 6 servicios de la barbería, con precios en pesos colombianos. */
    public function run(): void
    {
        $servicios = [
            ['Corte clásico', 45, 25000],
            ['Corte + barba', 60, 50000],
            ['Barba', 30, 25000],
            ['Diseño de cejas', 15, 15000],
            ['Corte infantil', 40, 20000],
            ['Corte + diseño', 75, 35000],
        ];

        foreach ($servicios as [$nombre, $duracion, $precio]) {
            Servicio::factory()->create([
                'nombre' => $nombre,
                'duracion_min' => $duracion,
                'precio' => $precio,
            ]);
        }
    }
}
