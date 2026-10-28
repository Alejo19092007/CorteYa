<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos BASE que necesita el sistema para funcionar en un servidor real: la cuenta del
 * administrador y el catálogo de servicios. El servidor lo ejecuta en cada arranque
 * (es idempotente) porque el plan gratuito de Render no da acceso a una terminal.
 *
 * NO incluye barberos, clientes ni turnos: esos son datos de demostración (ver DemoSeeder).
 */
class ProduccionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            ServicioSeeder::class,
        ]);
    }
}
