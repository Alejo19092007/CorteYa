<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de DEMOSTRACIÓN para la sustentación: barberos, clientes, ~60 días de historial y
 * los turnos de hoy. Necesita que ya existan el administrador y los servicios (ProduccionSeeder).
 * Usa Faker, así que se ejecuta desde el computador de desarrollo, no dentro del servidor.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BarberoSeeder::class,
            ClienteSeeder::class,
            TurnoHistorialSeeder::class,
            TurnoDemoSeeder::class,
        ]);

        $this->recalcularFidelidad();
    }

    /**
     * Deja clientes.turnos_atendidos igual al número REAL de turnos atendidos de cada cliente,
     * así el contador (regla R5) nunca contradice el historial.
     */
    private function recalcularFidelidad(): void
    {
        DB::table('clientes')->update([
            'turnos_atendidos' => DB::raw(
                "(SELECT COUNT(*) FROM turnos WHERE turnos.cliente_id = clientes.id AND turnos.estado = 'atendido')"
            ),
        ]);
    }
}
