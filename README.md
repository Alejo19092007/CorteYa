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

En construcción. Entrega actual: S9 (idea + documento de arquitectura C4).

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

En un servidor público se definen `SEED_ADMIN_PASSWORD` y `SEED_BARBERO_PASSWORD` en las variables de entorno para que **no** se usen estas.

## Datos de demo

El seeder también crea servicios, clientes, ~60 días de historial para el dashboard y los turnos de **hoy** calculados respecto a la hora actual (uno para marcar atendido, uno para no asistió, uno pendiente por confirmar y uno futuro). Antes de la sustentación:

```bash
php artisan migrate:fresh --seed   # borra toda la base y la reconstruye
```

## Créditos

La imagen de fondo del login es provisional (referencia de diseño); se reemplazará por una foto real de la barbería o una imagen con licencia libre.
