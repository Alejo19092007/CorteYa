# CorteYa

Sistema de turnos para una barbería de Bucaramanga. Proyecto de aula de **Desarrollo y Arquitectura Backend** (UNAB, segundo corte).

**Integrante:** Edwar Alejandro Jiménez Ríos.

## Piezas

| Carpeta | Descripción |
|---|---|
| `backend/` | Laravel 12: panel web (Blade + Bootstrap 5) y API REST con Sanctum |
| `cliente/` | Bot de WhatsApp (Node.js) que consume la API |
| `docs/` | Documento de arquitectura (C4) |
| `postman/` | Colección de Postman |

## Estado

En construcción. Entregas cerradas: `docs` (arquitectura C4), `v0.1` (modelo de datos) y `v0.2` (panel: login con roles, dashboard y CRUD). En curso: `v0.3` (API con Sanctum y despliegue).

## Desarrollo local

Requisitos: PHP 8.2+, Composer, Node.js, MySQL.

```bash
cd backend
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Usuarios de prueba

`php artisan migrate --seed` crea estos usuarios (contraseñas de **desarrollo**):

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | `admin@corteya.com` | `admin12345` |
| Barbero | `andres@corteya.com` | `barbero12345` |
| Barbero | `camilo@corteya.com` | `barbero12345` |
| Barbero | `diego@corteya.com` | `barbero12345` |

En un servidor público se definen `SEED_ADMIN_PASSWORD` y `SEED_BARBERO_PASSWORD` en las variables de entorno para que **no** se usen estas. En producción son **obligatorias**: sin ellas el sistema se niega a crear las cuentas.

## Datos base y datos de demo

Los seeders se dividen en dos grupos:

| Grupo | Qué crea | Cuándo |
|---|---|---|
| **Base** (`ProduccionSeeder`) | La cuenta del administrador y los 6 servicios | En cada arranque del servidor. Es repetible: no duplica ni pisa los precios que edite el dueño |
| **Demo** (`DemoSeeder`) | 3 barberos con horario, 25 clientes, ~60 días de historial y los turnos de **hoy** calculados respecto a la hora actual | Solo cuando se pide: `php artisan demo:cargar` |

En desarrollo, `php artisan migrate --seed` carga los dos grupos. Antes de la sustentación:

```bash
php artisan migrate:fresh --seed   # borra toda la base y la reconstruye
```

`demo:cargar` se niega a mezclar datos de demo con datos reales: solo funciona sobre una base sin clientes ni turnos.

## Despliegue (Render + Aiven)

- **Base de datos:** MySQL 8.4 gratuito de [Aiven](https://aiven.io), con conexión cifrada y verificada con el certificado `backend/certs/aiven-ca.pem` (es público).
- **Panel y API:** servicio web de [Render](https://render.com) que construye `backend/Dockerfile` (PHP 8.2 + Apache). Al arrancar ejecuta las migraciones y carga los datos base.

### 1. Crear el servicio en Render
*New → Web Service →* conectar este repositorio y configurar:

| Campo | Valor |
|---|---|
| Root Directory | `backend` |
| Runtime | `Docker` |
| Instance Type | `Free` |
| Health Check Path | `/up` |

### 2. Variables de entorno
Se cargan en el panel de Render (*Environment*), **nunca** en el repositorio:

| Variable | Valor |
|---|---|
| `APP_KEY` | Se genera en el computador con `php artisan key:generate --show` |
| `APP_URL` | La URL pública, con `https://` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` / `DB_PORT` | Los de la consola de Aiven |
| `DB_DATABASE` / `DB_USERNAME` | `defaultdb` / `avnadmin` |
| `DB_PASSWORD` | La contraseña de Aiven |
| `MYSQL_ATTR_SSL_CA` | `/var/www/html/certs/aiven-ca.pem` |
| `SEED_ADMIN_PASSWORD` | La contraseña que tendrá `admin@corteya.com` |
| `SESSION_SECURE_COOKIE` | `true` |

La imagen ya trae por defecto `APP_ENV=production`, `APP_DEBUG=false`, `APP_LOCALE=es` y `LOG_CHANNEL=stderr` (los errores se ven en los logs de Render).

### 3. Cargar los datos de demo (opcional)
El plan gratuito de Render no da terminal, así que la demo se carga **desde el computador de desarrollo** apuntando a la base de Aiven (las variables solo viven en esa ventana de PowerShell):

```powershell
cd backend
$env:DB_HOST='<host de Aiven>'; $env:DB_PORT='<puerto>'; $env:DB_DATABASE='defaultdb'; $env:DB_USERNAME='avnadmin'
$env:DB_PASSWORD='<contraseña de Aiven>'; $env:MYSQL_ATTR_SSL_CA="$PWD\certs\aiven-ca.pem"
$env:SEED_BARBERO_PASSWORD='<contraseña para los barberos de demo>'
php artisan demo:cargar
```

Sobre una base remota, `demo:cargar` **exige** `SEED_BARBERO_PASSWORD` para que las cuentas de demo no queden con las contraseñas por defecto de este README.

### Cosas que conviene saber
- El plan gratuito **duerme el servicio tras 15 minutos sin visitas** y tarda cerca de un minuto en despertar. Antes de la sustentación hay que abrir la URL unos minutos antes.
- Aiven puede apagar la base gratuita si no tiene actividad (avisa antes); se enciende de nuevo desde su consola.
- Para probar la imagen en local: `docker build -t corteya-web backend`.

## Créditos

La imagen de fondo del login es provisional (referencia de diseño); se reemplazará por una foto real de la barbería o una imagen con licencia libre.
