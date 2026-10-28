#!/bin/sh
# Arranque del contenedor de CorteYa. Se ejecuta cada vez que Render (re)inicia el servicio.
set -e

if [ -z "$APP_KEY" ]; then
    echo "ERROR: falta la variable de entorno APP_KEY." >&2
    echo "Genérala en tu computador con: php artisan key:generate --show" >&2
    exit 1
fi

# Render indica en qué puerto debe escuchar el servicio mediante la variable PORT
PORT="${PORT:-80}"
echo "Listen ${PORT}" > /etc/apache2/ports.conf
sed -i -E "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

cd /var/www/html

# Cachés de rendimiento: leen las variables de entorno que Render ya definió
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Estructura de la base de datos y datos base (administrador y servicios). Son idempotentes:
# repetirlos en cada arranque no duplica ni pisa nada. El plan gratuito de Render no da terminal,
# por eso se hace aquí.
php artisan migrate --force
php artisan db:seed --class=ProduccionSeeder --force

exec apache2-foreground
