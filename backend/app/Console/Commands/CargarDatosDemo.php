<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Turno;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ServicioSeeder;
use Illuminate\Console\Command;

class CargarDatosDemo extends Command
{
    protected $signature = 'demo:cargar';

    protected $description = 'Carga los barberos, clientes y turnos de demostración (para la sustentación)';

    public function handle(): int
    {
        // Nunca se mezclan datos de demo con datos reales: solo se carga sobre una base sin clientes ni turnos
        if (Cliente::exists() || Turno::exists()) {
            $this->error('Ya hay clientes o turnos en esta base de datos: no se mezclan datos de demo con datos reales.');
            $this->line('Para empezar de cero: php artisan migrate:fresh --seed (BORRA toda la base).');

            return self::FAILURE;
        }

        $conexion = config('database.default');
        $host = (string) config("database.connections.{$conexion}.host");
        // Una base SQLite (archivo, sin host) también es local; solo se desconfía de un host de red distinto del propio equipo
        $esLocal = $host === '' || in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
        $hayAdmin = User::where('rol', User::ROL_ADMIN)->exists();

        // En una base remota (la del servidor público) las cuentas de demo NO pueden quedar con claves conocidas
        if (! $esLocal) {
            $faltan = array_filter([
                blank(config('corteya.seed_barbero_password')) ? 'SEED_BARBERO_PASSWORD' : null,
                (! $hayAdmin && blank(config('corteya.seed_admin_password'))) ? 'SEED_ADMIN_PASSWORD' : null,
            ]);

            if ($faltan) {
                $this->error("Vas a cargar datos en una base REMOTA ({$host}). Define antes: ".implode(' y ', $faltan));
                $this->line('Así las cuentas de demo no quedan con las contraseñas por defecto del README.');

                return self::FAILURE;
            }
        }

        // Los servicios (y el administrador, si falta) son la base sobre la que se apoya la demo
        $this->callSilently('db:seed', ['--class' => ServicioSeeder::class, '--force' => true]);
        if (! $hayAdmin) {
            $this->callSilently('db:seed', ['--class' => AdminSeeder::class, '--force' => true]);
        }

        $this->info("Cargando datos de demo en {$host}...");
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->info('Listo: barberos, clientes, historial de 60 días y turnos de hoy.');

        return self::SUCCESS;
    }
}
