<?php

return [

    /*
    | Contraseñas de las cuentas que crean los seeders. Se leen desde la configuración (y no con
    | env() dentro del seeder) porque en producción la configuración queda en caché y env() solo
    | funciona de forma fiable dentro de los archivos de config.
    | En producción son OBLIGATORIAS: no se usan contraseñas por defecto en un servidor público.
    */
    'seed_admin_password' => env('SEED_ADMIN_PASSWORD'),
    'seed_barbero_password' => env('SEED_BARBERO_PASSWORD'),

];
