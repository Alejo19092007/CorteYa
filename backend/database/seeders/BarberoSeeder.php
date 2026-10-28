<?php

namespace Database\Seeders;

use App\Models\Barbero;
use App\Models\Horario;
use App\Models\User;
use Database\Seeders\Concerns\LeeContrasenas;
use Illuminate\Database\Seeder;

class BarberoSeeder extends Seeder
{
    use LeeContrasenas;

    /**
     * 3 barberos con su cuenta y su horario de 7 días (datos de DEMO).
     * No usa factories (Faker no existe en la imagen de producción) y se puede repetir sin duplicar.
     */
    public function run(): void
    {
        $contrasena = $this->contrasena('seed_barbero_password', 'SEED_BARBERO_PASSWORD', 'barbero12345');

        $barberos = [
            ['Andrés Gómez', 'andres@corteya.com', '573101110001'],
            ['Camilo Rojas', 'camilo@corteya.com', '573101110002'],
            ['Diego Herrera', 'diego@corteya.com', '573101110003'],
        ];

        foreach ($barberos as [$nombre, $correo, $telefono]) {
            // firstOrCreate: si la cuenta ya existe no se toca (el administrador pudo cambiarle la clave)
            $user = User::firstOrCreate(
                ['email' => $correo],
                ['name' => $nombre, 'password' => $contrasena, 'rol' => User::ROL_BARBERO]
            );

            $barbero = Barbero::firstOrCreate(['user_id' => $user->id], ['telefono' => $telefono, 'activo' => true]);

            // Los 7 días de la semana (1 = lunes ... 7 = domingo), de 6:00 a 22:00,
            // para que R1 no rechace los turnos de demo a ninguna hora del día.
            foreach (range(1, 7) as $dia) {
                Horario::firstOrCreate(
                    ['barbero_id' => $barbero->id, 'dia_semana' => $dia],
                    ['hora_inicio' => '06:00:00', 'hora_fin' => '22:00:00']
                );
            }
        }
    }
}
