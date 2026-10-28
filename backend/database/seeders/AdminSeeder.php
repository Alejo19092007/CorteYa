<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\LeeContrasenas;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    use LeeContrasenas;

    /**
     * Cuenta del administrador (dueño). Es idempotente: se puede ejecutar en cada arranque del
     * servidor sin duplicarla; la contraseña siempre es la de la variable SEED_ADMIN_PASSWORD.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@corteya.com'],
            [
                'name' => 'Juan Pérez',
                'password' => $this->contrasena('seed_admin_password', 'SEED_ADMIN_PASSWORD', 'admin12345'),
                'rol' => User::ROL_ADMIN,
            ]
        );
    }
}
