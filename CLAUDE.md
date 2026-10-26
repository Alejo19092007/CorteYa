# CorteYa — Contexto del proyecto

Proyecto de aula de **Desarrollo y Arquitectura Backend** (UNAB, segundo corte, 30%).
Integrantes: Edwar Alejandro Jiménez Ríos y Julián (confirmar apellidos).
Responde siempre en español, con pasos concretos y comandos reales, y explica brevemente el "por qué".

## La idea

Sistema de turnos para una barbería de Bucaramanga:
- El **dueño** y los **barberos** gestionan todo en un **panel web** (Laravel + Blade + Bootstrap).
- Los **clientes** reservan, consultan y cancelan turnos por **WhatsApp** (bot que consume la API).

Pendiente: nombre real de la barbería y entrevista al dueño (evidencia).

## Arquitectura (C4)

- **Panel web** — Laravel 12, Blade, Bootstrap 5. Roles: `admin` y `barbero`.
- **API REST** — mismo proyecto Laravel, rutas `/api`, protegida con **Sanctum**, respuestas con **API Resources**.
- **Bot de WhatsApp** — app aparte en `/cliente` (Node.js + Express), usa la **WhatsApp Cloud API oficial de Meta** (webhook HTTPS). Inicia sesión en la API y usa el token Sanctum. Es un *contenedor del sistema*, no un sistema externo.
- **Base de datos** — MySQL en la nube.

Flujo obligatorio en Laravel:
`routes (web.php / api.php) → Controller → Form Request (valida) → Service (reglas) → Repository (consultas Eloquent) → Model → MySQL`

Los controladores NO llevan lógica de negocio. Si una regla falla, el Service lanza una excepción que la API devuelve como **422**.

### Componentes con nombres reales
- Controladores panel: `DashboardController`, `BarberoController`, `ServicioController`, `TurnoController`
- Controladores API: `AuthController`, `TurnoApiController`, `ServicioApiController`
- Resources: `TurnoResource`, `ServicioResource`
- Form Requests: `StoreTurnoRequest`, `UpdateTurnoRequest`, `StoreServicioRequest`, `StoreBarberoRequest`
- Services: `TurnoService` (R1–R4), `FidelidadService` (R5), `DashboardService`
- Repositories: `TurnoRepository`, `BarberoRepository`, `ClienteRepository`
- Modelos: `User`, `Barbero`, `Cliente`, `Servicio`, `Horario`, `Turno`

## Reglas de negocio

| Regla | Descripción | Si falla |
|---|---|---|
| R1 Sin cruces | Un barbero no puede tener dos turnos solapados; el turno debe caer dentro de su horario de ese día | 422 |
| R2 Cálculo | `hora_fin` = inicio + suma de duraciones; `precio_total` = suma de precios. Nunca se digitan | Se calcula en el Service |
| R3 Cancelación | Solo hasta 2 horas antes del inicio | 422 |
| R4 Estados | pendiente → confirmado → atendido; pendiente/confirmado → cancelado; confirmado → no_asistio. No se sale de atendido ni cancelado | 422 |
| R5 Fidelidad | Cada 10 turnos atendidos, el siguiente sale con precio 0 | Automático al crear |

Opcional (decidir tras la entrevista): comisiones por barbero.

## Modelo de datos

- `users`: id, name, email (único), password, rol (admin | barbero)
- `barberos`: id, user_id (FK único → users, **1:1**), telefono, activo
- `horarios`: id, barbero_id (FK, **1:N**), dia_semana (1–7), hora_inicio, hora_fin
- `clientes`: id, nombre, telefono (único, con 57), turnos_atendidos
- `servicios`: id, nombre, duracion_min, precio, activo
- `turnos`: id, barbero_id (FK), cliente_id (FK), fecha, hora_inicio, hora_fin, precio_total, estado (enum), origen (panel | whatsapp)
- `turno_servicio` (pivote **N:M** turnos–servicios): turno_id, servicio_id, precio, duracion_min (guardan el valor al momento de reservar)

## API REST

| Método | Ruta | Respuestas |
|---|---|---|
| POST | /api/login | 200, 401, 422 |
| POST | /api/logout | 204, 401 |
| GET | /api/servicios | 200, 401 |
| POST | /api/servicios | 201, 401, 422 |
| PUT | /api/servicios/{id} | 200, 401, 404, 422 |
| DELETE | /api/servicios/{id} | 204, 401, 404 |
| GET | /api/barberos/{id}/disponibilidad?fecha= | 200, 401, 404 |
| GET | /api/turnos?telefono= | 200, 401 |
| POST | /api/turnos | 201, 401, 422 |
| PUT | /api/turnos/{id} | 200, 401, 404, 422 |
| DELETE | /api/turnos/{id} (cancela, R3) | 204, 401, 404, 422 |

## Funciones por usuario

- **Dueño (admin):** barberos, servicios y precios, horarios, agenda de todos, clientes y fidelidad, dashboard de ingresos (gráfico).
- **Barbero:** su agenda del día, marcar atendido / no asistió, sus cortes del mes. No ve a otros barberos ni reportes.
- **Cliente (WhatsApp):** ver servicios, reservar, consultar y cancelar su turno, cortes que faltan para el gratis.
- Fuera de alcance: pagos en línea, inventario, varias sedes, recordatorios automáticos.

## Requisitos de la rúbrica (checklist)

Backend: 5+ entidades con migración, modelo y FKs · relaciones 1:1, 1:N y N:M · factories y seeders (`php artisan migrate --seed`) · rutas con nombre y grupos, controladores de recurso · Form Requests · Service + Repository · API REST de 2 entidades con API Resources · Sanctum y códigos 200, 201, 204, 401, 404, 422.

Frontend: login con 2 roles · dashboard con 1 gráfico · 3 CRUD con búsqueda y paginación · Blade + Bootstrap responsive · páginas 404 y 500 propias · muestra los datos que crea el bot.

Cliente: fuera de Laravel en `/cliente` · inicia sesión y usa el token · 1 GET y 1 escritura · URL pública (nunca localhost) · maneja sin conexión, 401 y 422 · sus datos se ven en el panel.

Despliegue y Git: URL pública con HTTPS · BD en la nube · `.env` nunca en GitHub (solo `.env.example`) · ramas `feature/...` y Pull Requests · commits de ambos · una etiqueta por entrega · README y colección de Postman.

## Diseño del panel

Estilo: barbería clásica y moderna. Navegación oscura, área de trabajo clara.

- Colores: carbón `#1C1C1E` (sidebar, encabezados) · dorado latón `#C8A15A` (acento, `btn-primary`) · crema `#F7F4EF` (fondo).
- Tipografía: **Oswald** para títulos y logo, **Inter** para texto y tablas (Google Fonts).
- Íconos: Bootstrap Icons. Gráficos: Chart.js con barras doradas.
- Estados del turno (badges, siempre iguales): pendiente = gris, confirmado = azul, atendido = verde, cancelado = rojo, no_asistio = naranja.
- Personalización: `resources/css/corteya.css` sobrescribe variables de Bootstrap (`--bs-primary`, `--bs-body-bg`, `--bs-body-font-family`); se carga en el layout principal `layouts/app.blade.php`.
- Layout: sidebar a la izquierda en escritorio, menú colapsable en celular.

Pantallas:
1. Login: fondo carbón, tarjeta clara centrada con logo.
2. Dashboard (admin): 4 tarjetas (turnos de hoy, ingresos del mes, clientes nuevos, cancelaciones) + gráfico de ingresos por barbero.
3. Agenda del barbero: mobile first, lista del día por hora, botones grandes "Atendido" / "No asistió".
4. CRUD de barberos, servicios y turnos: buscador, filtros por estado y fecha, paginación.
5. Nuevo turno: hora fin, duración y precio total calculados en vivo (R2); el error de R1 se muestra como alerta.
6. Páginas 404 ("Este corte no existe") y 500 con el mismo estilo y botón para volver.

Mockups de referencia: `docs/diseño/CorteYa_Diseno_Pantallas.docx` (computador 1440 px y celular 390 px por pantalla).
Al construir las vistas a partir de los mockups, aplicar SIEMPRE estas correcciones (los mockups tienen errores):
- El menú del rol `barbero` solo tiene "Mi agenda" y "Cerrar sesión". No ve Barberos, Servicios, Horarios ni Clientes.
- Botones "Atendido" / "No asistió" solo en turnos `confirmado` (R4).
- En el listado, la acción roja es "Cancelar" (cambia el estado, ícono `bi-x-circle`), nunca eliminar; solo en turnos `pendiente` o `confirmado`.
- Dashboard: una baja en cancelaciones se muestra en verde; la fecha es la del día actual.
- Login sin "¿Olvidaste tu contraseña?" (fuera de alcance).
- Fondo del login: foto real de la barbería o imagen con licencia libre.
- El gráfico siempre en dorado `#C8A15A`, nunca azul.

## Estructura del repositorio

```
corteya/
├── backend/      proyecto Laravel: panel + API
├── cliente/      bot de WhatsApp (Node.js)
├── docs/arquitectura.pdf
├── postman/
└── README.md
```

## Entregas

1. S9 · docs — idea + C4 en `/docs/arquitectura.pdf` ← **ENTREGA ACTUAL / URGENTE**. Contiene solo: la idea, C4 nivel 1 (contexto), nivel 2 (contenedores), nivel 3 (componentes) y modelo de datos con diccionario. Falta: nombre real de la barbería, entrevista al dueño, crear el repo y subir el PDF.
2. S10–11 · v0.1 — migraciones, modelos, factories y seeders
3. S12–13 · v0.2 — login con roles, dashboard y CRUD
4. S14 · v0.3 — API con Sanctum, Postman y URL pública
5. S15 · v0.4 — bot de WhatsApp conectado a la URL pública
6. S16 · v1.0 — sustentación en vivo (cualquiera de los dos debe poder explicar y modificar cualquier parte)

## Entorno local (Windows)

- XAMPP con PHP 8.2.12, Composer, Node.js, Laravel 12. Proyectos en `C:\backend\` (nunca dentro de OneDrive).
- PHP no está en el PATH: usar `C:\xampp\php\php.exe artisan ...` hasta agregar `C:\xampp\php` a las variables de entorno.
- Laravel 13 exige PHP 8.3+, por eso se usa Laravel 12.
