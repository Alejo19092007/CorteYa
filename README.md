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

En construcción. Entregas cerradas: `docs` (arquitectura C4), `v0.1` (modelo de datos), `v0.2` (panel: login con roles, dashboard y CRUD) y `v0.3` (API con Sanctum, colección de Postman y despliegue público en Render + Aiven: https://corteya.onrender.com). En curso: `v0.4` (bot de WhatsApp en `cliente/`).

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

## API REST

Base: `https://corteya.onrender.com/api` (en local, `http://127.0.0.1:8000/api`). Autenticación con **Laravel Sanctum**: se inicia sesión en `POST /api/login`, que devuelve un token, y las demás rutas lo exigen en la cabecera `Authorization: Bearer <token>`. Todas las respuestas son JSON.

| Método | Ruta | Qué hace | Códigos |
|---|---|---|---|
| POST | `/api/login` | Devuelve el token | 200, 401, 403, 422, 429 |
| POST | `/api/logout` | Revoca el token usado | 204, 401 |
| GET | `/api/servicios` | Lista servicios (`?activo=1` solo los que se ofrecen) | 200, 401 |
| POST | `/api/servicios` | Crea un servicio | 201, 401, 422 |
| PUT | `/api/servicios/{id}` | Edita un servicio | 200, 401, 404, 422 |
| DELETE | `/api/servicios/{id}` | Borra un servicio que nunca se usó | 204, 401, 404, 422 |
| GET | `/api/barberos` | Barberos activos (para que el bot los ofrezca) | 200, 401 |
| GET | `/api/barberos/{id}/disponibilidad?fecha=&servicios[]=` | Horas libres ese día | 200, 401, 404, 422 |
| GET | `/api/turnos?telefono=` | Turnos del cliente (`&estado=` opcional) | 200, 401, 422 |
| POST | `/api/turnos` | Reserva un turno (nace `pendiente`, origen `whatsapp`) | 201, 401, 422 |
| PUT | `/api/turnos/{id}` | Reprograma un turno pendiente o confirmado | 200, 401, 404, 422 |
| DELETE | `/api/turnos/{id}` | Cancela el turno (no lo borra); aplica R3 | 204, 401, 404, 422 |

Decisiones:
- **Solo cuentas de administrador** reciben token (los barberos usan el panel). Otra cuenta recibe 403 al iniciar sesión.
- El cliente se identifica por su **teléfono** (acepta `3001234567` o `573001234567`); si escribe por primera vez se registra con el `nombre` que se envíe.
- `hora_fin` y `precio_total` **nunca** se envían: los calcula el sistema (R2). El precio y la duración quedan congelados en el turno.
- Las reglas de negocio fallan con **422** y un `message` (R1 cruce o fuera de jornada, R3 cancelar con menos de 2 h, R4 cambio de estado no permitido). Son las mismas del panel porque ambos usan `TurnoService`.
- Un nuevo login con el mismo `device_name` reemplaza al token anterior; los tokens se guardan con hash. El login admite 10 intentos por minuto.

### Colección de Postman
En [postman/](postman/): `CorteYa.postman_collection.json` y dos entornos (`produccion` y `local`). Se importan los tres archivos, se elige el entorno y se escribe la contraseña del administrador en la variable `password` (no se guarda en el repositorio). Con *Runner* se ejecuta toda la colección en orden: 27 peticiones, 45 verificaciones de código y de contenido, e incluye 401, 404, 422, R1, R3 y R4. Necesita al menos un barbero activo con horario (ver `demo:cargar`).

Por consola: `npx newman run postman/CorteYa.postman_collection.json -e postman/CorteYa.local.postman_environment.json --env-var password=<clave>`.

## Créditos

La imagen de fondo del login es provisional (referencia de diseño); se reemplazará por una foto real de la barbería o una imagen con licencia libre.
