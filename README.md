# CorteYa

Sistema de turnos para una barbería de Bucaramanga. Proyecto de aula de **Desarrollo y Arquitectura Backend** (UNAB, segundo corte).

**Integrantes:** Edwar Alejandro Jiménez Ríos y Julián.

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

## Créditos

La imagen de fondo del login es provisional (referencia de diseño); se reemplazará por una foto real de la barbería o una imagen con licencia libre.
