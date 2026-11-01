<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class BotSeeder extends Seeder
{
    /** Correo de la cuenta con la que el bot inicia sesión en la API. */
    public const EMAIL = 'bot@corteya.com';

    /**
     * Cuenta de servicio del bot de Telegram. Existe para que el bot NO use la cuenta personal del
     * dueño: si la variable de entorno del bot se filtrara, el dueño no pierde su contraseña y la
     * del bot se puede cambiar sin afectarlo. La API pide rol administrador, por eso lo tiene.
     *
     * Es idempotente (se ejecuta en cada arranque del servidor). La contraseña sale de SEED_BOT_PASSWORD.
     * A diferencia del administrador, en producción es OPCIONAL: sin la variable no se crea la cuenta
     * (así un servidor que ya estaba en marcha sigue arrancando hasta que se configure el bot).
     */
    public function run(): void
    {
        $contrasena = config('corteya.seed_bot_password');

        if (blank($contrasena)) {
            if (app()->environment('production')) {
                $this->command?->warn('SEED_BOT_PASSWORD no está definida: no se crea la cuenta del bot.');

                return;
            }

            $contrasena = 'bot12345'; // solo desarrollo (ver README)
        }

        User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Bot de turnos',
                'password' => $contrasena,
                'rol' => User::ROL_ADMIN,
            ]
        );
    }
}
