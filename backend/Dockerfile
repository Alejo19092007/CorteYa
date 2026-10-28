# Imagen de CorteYa (panel + API) para desplegar en Render.
# PHP 8.2 con Apache, el mismo PHP que se usa en desarrollo.
FROM php:8.2-apache

# Extensiones que necesita Laravel con MySQL (pdo_mysql) y para que Composer descomprima paquetes (zip).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install pdo_mysql zip bcmath \
    && rm -rf /var/lib/apt/lists/*

# Apache: reescritura de URLs (rutas de Laravel) y raíz del sitio en /public (nunca en la raíz del proyecto)
RUN a2enmod rewrite headers
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# Primero solo las dependencias: Docker reutiliza esta capa mientras composer.lock no cambie
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

# Después el código. Las dependencias de desarrollo (Faker, PHPUnit) NO van en la imagen de producción.
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

# Valores por defecto seguros; Render puede sobrescribirlos con sus variables de entorno.
# LOG_CHANNEL=stderr hace que los errores salgan por la consola del contenedor (visibles en los logs de Render).
# APP_LOCALE=es: en el servidor no hay archivo .env, y sin esto Laravel arrancaría en inglés.
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_LOCALE=es \
    APP_FALLBACK_LOCALE=en \
    LOG_CHANNEL=stderr

EXPOSE 10000
CMD ["entrypoint.sh"]
