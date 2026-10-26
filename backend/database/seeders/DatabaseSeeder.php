<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Orden obligatorio por las llaves foráneas: primero quienes no dependen de nadie
     * (usuarios, servicios, clientes) y después los turnos, que apuntan a todos ellos.
     */
    public function run(): void
    {
        $this->call([
            UsuarioSeeder::class,
            ServicioSeeder::class,
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
