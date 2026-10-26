<?php

namespace Database\Seeders;

use App\Models\Barbero;
use App\Models\Horario;
use App\Models\User;
use Illuminate\Database\Seeder;

class UsuarioSeeder extends Seeder
{
    /**
     * 1 administrador y 3 barberos (cada uno con su usuario y su horario de 7 días).
     *
     * Las contraseñas salen de variables de entorno para que en producción NO sean las
     * conocidas del README. Si no existen (uso local), se usan los valores por defecto.
     */
    public function run(): void
    {
        // "?:" (y no el segundo parámetro de env) porque una variable vacía en .env
        // devuelve '' y no el valor por defecto: dejaría la cuenta sin contraseña.
        $passAdmin = env('SEED_ADMIN_PASSWORD') ?: 'admin12345';
        $passBarbero = env('SEED_BARBERO_PASSWORD') ?: 'barbero12345';

        User::factory()->admin()->create([
            'name' => 'Juan Pérez',
            'email' => 'admin@corteya.com',
            'password' => $passAdmin, // el cast "hashed" del modelo la cifra
        ]);

        $barberos = [
            ['Andrés Gómez', 'andres@corteya.com', '573101110001'],
            ['Camilo Rojas', 'camilo@corteya.com', '573101110002'],
            ['Diego Herrera', 'diego@corteya.com', '573101110003'],
        ];

        foreach ($barberos as [$nombre, $correo, $telefono]) {
            $user = User::factory()->barbero()->create([
                'name' => $nombre,
                'email' => $correo,
                'password' => $passBarbero,
            ]);

            $barbero = Barbero::factory()->create([
                'user_id' => $user->id,
                'telefono' => $telefono,
            ]);

            // Los 7 días de la semana (1 = lunes ... 7 = domingo), de 6:00 a 22:00,
            // para que R1 no rechace los turnos de demo a ninguna hora del día.
            foreach (range(1, 7) as $dia) {
                Horario::factory()->create([
                    'barbero_id' => $barbero->id,
                    'dia_semana' => $dia,
                    'hora_inicio' => '06:00:00',
                    'hora_fin' => '22:00:00',
                ]);
            }
        }
    }
}
