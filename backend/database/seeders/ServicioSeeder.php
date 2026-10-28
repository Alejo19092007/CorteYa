<?php

namespace Database\Seeders;

use App\Models\Servicio;
use Illuminate\Database\Seeder;

class ServicioSeeder extends Seeder
{
    /**
     * Los 6 servicios de la barbería, con precios en pesos colombianos.
     *
     * Solo se cargan si la tabla está VACÍA. El servidor ejecuta este seeder en cada arranque; si
     * se reescribiera siempre, borraría los precios que el dueño haya editado en el panel o
     * volvería a crear un servicio que decidió eliminar.
     */
    public function run(): void
    {
        if (Servicio::exists()) {
            return;
        }

        $servicios = [
            ['Corte clásico', 45, 25000],
            ['Corte + barba', 60, 50000],
            ['Barba', 30, 25000],
            ['Diseño de cejas', 15, 15000],
            ['Corte infantil', 40, 20000],
            ['Corte + diseño', 75, 35000],
        ];

        foreach ($servicios as [$nombre, $duracion, $precio]) {
            Servicio::create([
                'nombre' => $nombre,
                'duracion_min' => $duracion,
                'precio' => $precio,
                'activo' => true,
            ]);
        }
    }
}
