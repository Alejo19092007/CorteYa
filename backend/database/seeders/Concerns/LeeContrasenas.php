<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

trait LeeContrasenas
{
    /**
     * Contraseña de las cuentas que crea un seeder.
     * Si no se definió la variable de entorno: en desarrollo se usa la contraseña por defecto
     * del README; en PRODUCCIÓN se aborta, para no dejar una cuenta pública con una clave conocida.
     */
    protected function contrasena(string $clave, string $variable, string $porDefecto): string
    {
        $valor = config("corteya.{$clave}");

        if (filled($valor)) {
            return $valor;
        }

        if (app()->environment('production')) {
            throw new RuntimeException("Falta la variable de entorno {$variable}: en producción no se usan contraseñas por defecto.");
        }

        return $porDefecto;
    }
}
