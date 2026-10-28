# CorteYa — Contexto del proyecto

Proyecto de aula de **Desarrollo y Arquitectura Backend** (UNAB, segundo corte, 30%).
Integrante: **Edwar Alejandro Jiménez Ríos** (proyecto individual, aprobado por el profesor).
Responde siempre en español, con pasos concretos y comandos reales, y explica brevemente el "por qué". La sustentación es individual: explicar cada parte que se construya y su razón, para que el integrante pueda defender y modificar cualquier archivo en vivo.

## Equipo y flujo de trabajo

- Todos los commits son del integrante, pero se mantiene el flujo que evalúa la rúbrica: ramas `feature/...`, Pull Requests a `main` y una etiqueta por entrega.
- Nada directo a `main`.
- Commits separados por tema (migraciones, modelos, factories, seeders...).

## La idea

Sistema de turnos para una barbería de Bucaramanga:
- El **dueño** y los **barberos** gestionan todo en un **panel web** (Laravel + Blade + Bootstrap).
- Los **clientes** reservan, consultan y cancelan turnos por **WhatsApp** (bot que consume la API).

La entrevista al dueño (evidencia) y el nombre real de la barbería se harán más adelante.

## Alcance y prioridades

- **Obligatorio:** todo lo de la rúbrica + reglas R1, R2, R3 y R4.
- **Opcional, al final si sobra tiempo:** R5 (fidelidad), "sus cortes del mes" del barbero, crear clientes desde el formulario de turno.
- **Fuera de alcance:** comisiones, pagos en línea, inventario, varias sedes, recordatorios automáticos, "¿Olvidaste tu contraseña?".

## Arquitectura (C4)

- **Panel web** — Laravel 12, Blade, Bootstrap 5. Roles: `admin` y `barbero`.
- **API REST** — mismo proyecto Laravel, rutas `/api`, protegida con **Sanctum**, respuestas con **API Resources**.
- **Bot de WhatsApp** — app aparte en `/cliente` (Node.js + Express), usa la **WhatsApp Cloud API oficial de Meta** (webhook HTTPS). Inicia sesión en la API y usa el token Sanctum. Es un *contenedor del sistema*, no un sistema externo.
- **Base de datos** — MySQL en la nube.

Flujo obligatorio en Laravel:
`routes (web.php / api.php) → Controller → Form Request (valida) → Service (reglas) → Repository (consultas Eloquent) → Model → MySQL`

Los controladores NO llevan lógica de negocio. Si una regla falla, el Service lanza una excepción que la API devuelve como **422** (y el panel muestra como error del formulario).

### Componentes con nombres reales
- Controladores panel: `AuthController`, `DashboardController`, `BarberoController`, `ServicioController`, `TurnoController`
- Controladores API: `AuthController`, `TurnoApiController`, `ServicioApiController`
- Resources: `TurnoResource`, `ServicioResource`
- Form Requests: `StoreTurnoRequest`, `UpdateTurnoRequest`, `StoreServicioRequest`, `StoreBarberoRequest`
- Services: `TurnoService` (R1–R4), `FidelidadService` (R5, opcional), `DashboardService`
- Repositories: `TurnoRepository`, `BarberoRepository`, `ClienteRepository`
- Modelos: `User`, `Barbero`, `Cliente`, `Servicio`, `Horario`, `Turno`

## Reglas de negocio

| Regla | Descripción | Si falla |
|---|---|---|
| R1 Sin cruces | Un barbero no puede tener dos turnos solapados; el turno debe caer dentro de su horario de ese día | 422 |
| R2 Cálculo | `hora_fin` = inicio + suma de duraciones; `precio_total` = suma de precios. Nunca se digitan | Se calcula en el Service |
| R3 Cancelación | Solo hasta 2 horas antes del inicio (solo para el cliente, ver decisión 1) | 422 |
| R4 Estados | pendiente → confirmado → atendido; pendiente/confirmado → cancelado; confirmado → no_asistio. No se sale de atendido ni cancelado | 422 |
| R5 Fidelidad *(opcional)* | Cada 10 turnos atendidos, el siguiente sale con precio 0 | Automático al crear |

### Decisiones tomadas

1. **R3 solo para el cliente (API/bot).** El admin puede cancelar en cualquier momento. `TurnoService::cancelar` recibe quién cancela.
2. **Estado inicial:** los turnos creados en el panel nacen `confirmado`; los creados por WhatsApp nacen `pendiente`.
3. **Confirmar pendientes:** el admin (cualquier turno, desde el listado) y el barbero (solo los suyos, botón "Confirmar" en "Mi agenda").
4. **"Atendido" y "No asistió"** solo se permiten cuando ya pasó la `hora_inicio` del turno; si no, 422 "Aún no ha llegado la hora del turno".
5. **Botones en "Mi agenda" según estado:** `pendiente` → Confirmar; `confirmado` → Atendido / No asistió (solo si ya pasó la hora); `atendido`, `cancelado` y `no_asistio` → sin botones.
6. **Nuevo turno:** el cliente se busca por nombre o teléfono (teléfono único). Crear el cliente desde el formulario es opcional (prioridad baja).

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
- **Barbero:** su agenda del día, confirmar / marcar atendido / no asistió (solo sus turnos), sus cortes del mes (opcional). No ve a otros barberos ni reportes.
- **Cliente (WhatsApp):** ver servicios, reservar, consultar y cancelar su turno, cortes que faltan para el gratis (R5, opcional).
- Fuera de alcance: ver "Alcance y prioridades".

## Requisitos de la rúbrica (checklist)

Backend: 5+ entidades con migración, modelo y FKs · relaciones 1:1, 1:N y N:M · factories y seeders (`php artisan migrate --seed`) · rutas con nombre y grupos, controladores de recurso · Form Requests · Service + Repository · API REST de 2 entidades con API Resources · Sanctum y códigos 200, 201, 204, 401, 404, 422.

Frontend: login con 2 roles · dashboard con 1 gráfico · 3 CRUD con búsqueda y paginación · Blade + Bootstrap responsive · páginas 404 y 500 propias · muestra los datos que crea el bot.

Cliente: fuera de Laravel en `/cliente` · inicia sesión y usa el token · 1 GET y 1 escritura · URL pública (nunca localhost) · maneja sin conexión, 401 y 422 · sus datos se ven en el panel.

Despliegue y Git: URL pública con HTTPS · BD en la nube · `.env` nunca en GitHub (solo `.env.example`) · ramas `feature/...` y Pull Requests · commits del integrante · una etiqueta por entrega · README y colección de Postman.

## Diseño del panel

Estilo: barbería clásica y moderna. Navegación oscura, área de trabajo clara.

- Colores: carbón `#1C1C1E` (sidebar, encabezados) · dorado latón `#C8A15A` (acento, `btn-primary`) · crema `#F7F4EF` (fondo).
- Tipografía: **Oswald** para títulos y logo, **Inter** para texto y tablas (Google Fonts).
- Íconos: Bootstrap Icons. Gráficos: Chart.js con barras doradas.
- Estados del turno (badges, siempre iguales): pendiente = gris, confirmado = azul, atendido = verde, cancelado = rojo, no_asistio = naranja.
- Personalización: `public/css/corteya.css` sobrescribe variables de Bootstrap (`--bs-primary`, `--bs-body-bg`, `--bs-body-font-family`); se carga en los layouts con `asset('css/corteya.css')`.
- Layout: sidebar a la izquierda en escritorio, menú colapsable en celular.

Decisión de frontend (se construye en la v0.2):
- Vistas Blade + **Bootstrap 5.3 por CDN (jsDelivr)**, Bootstrap Icons por CDN, Google Fonts (Oswald e Inter) y Chart.js por CDN.
- **No usar Vite, Tailwind ni starter kits** (Breeze, React, Vue, Livewire).
- CSS propio en `public/css/corteya.css`; JS propio en `public/js/`.
- Layout principal: `resources/views/layouts/app.blade.php` (con sidebar). Layout aparte para el login: `layouts/guest.blade.php`.
- El login y los roles se hacen a mano: `AuthController` del panel + middleware `role` (`admin` | `barbero`).

Pantallas:
1. Login: fondo carbón, tarjeta clara centrada con logo.
2. Dashboard (admin): 4 tarjetas (turnos de hoy, ingresos del mes, clientes nuevos, cancelaciones) + gráfico de ingresos por barbero.
3. Agenda del barbero: mobile first, lista del día por hora, botones grandes según estado (decisión 5).
4. CRUD de barberos, servicios y turnos: buscador, filtros por estado y fecha, paginación.
5. Nuevo turno: hora fin, duración y precio total calculados en vivo (R2); el error de R1 se muestra como alerta.
6. Páginas 404 ("Este corte no existe") y 500 con el mismo estilo y botón para volver.

Mockups de referencia: `docs/diseño/CorteYa_Diseno_Pantallas.docx` (computador 1440 px y celular 390 px por pantalla).
Al construir las vistas a partir de los mockups, aplicar SIEMPRE estas correcciones (los mockups tienen errores):
- El menú del rol `barbero` solo tiene "Mi agenda" y "Cerrar sesión". No ve Barberos, Servicios, Horarios ni Clientes.
- Botones "Atendido" / "No asistió" solo en turnos `confirmado` (R4) y si ya pasó la hora (decisión 4).
- En el listado, la acción roja es "Cancelar" (cambia el estado, ícono `bi-x-circle`), nunca eliminar; solo en turnos `pendiente` o `confirmado`.
- Dashboard: una baja en cancelaciones se muestra en verde; la fecha es la del día actual.
- Login sin "¿Olvidaste tu contraseña?" (fuera de alcance).
- Fondo del login: foto real de la barbería o imagen con licencia libre (la actual es provisional).
- El gráfico siempre en dorado `#C8A15A`, nunca azul.

## Datos de demo

Seeder de la v0.1 pensado para la sustentación. Los turnos de **hoy** se crean **relativos a `now()`**, no con horas fijas:

| Hora | Estado | Qué se demuestra |
|---|---|---|
| `now() - 2h` | `confirmado` | Marcar atendido |
| `now() - 1h` | `confirmado` | Marcar no asistió |
| `now() + 1h` | `pendiente`, origen `whatsapp` | Confirmar; y 422 de R3 al intentar cancelarlo como cliente (API/bot), porque faltan menos de 2 h |
| `now() + 3h` | `confirmado` | "Atendido" bloqueado (422 "Aún no ha llegado la hora del turno"); cancelación exitosa como cliente (faltan más de 2 h) |

Contenido del seeder:
- 1 admin y 3 barberos con usuario (contraseñas conocidas, documentadas en el README).
- Horarios de los barberos: los 7 días de la semana, de 6:00 a 22:00, para que R1 no rechace los turnos de demo.
- 6 servicios reales: corte clásico, corte + barba, barba, cejas, corte infantil, diseño (precios en pesos colombianos).
- Clientes y turnos de días anteriores en varios estados, para que el dashboard tenga datos.
- Los turnos del seeder respetan R1 (sin cruces) y R2 (`hora_fin` y `precio_total` calculados).
- *(Opcional, con R5)* Un cliente con 9 turnos atendidos **reales** en el historial (no solo `turnos_atendidos = 9`), para que dashboard y ficha cuadren; su siguiente turno sale con precio 0.

Reglas del seeder:
- **R3 solo aplica al cliente:** el 422 de R3 se demuestra por API/bot (Postman), nunca desde el panel como admin.
- **Cerca de medianoche:** si `now() ± Nh` cae en otro día o fuera de 6:00–22:00, el seeder omite o ajusta ese turno, para que R1 no lo rechace.
- **Sin cruces entre turnos de demo:** asignarlos a barberos distintos o con servicios cortos (≤ 60 min).
- Antes de la sustentación: `php artisan migrate:fresh --seed`. Borra toda la base: en producción exige `--force` y se usa solo en el despliegue de la demo, nunca con datos reales.

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

1. **S9 · docs** — idea + C4 en `/docs/arquitectura.pdf` ← **ENTREGA ACTUAL**. El documento ya está hecho fuera de VS Code (no armarlo ni duplicarlo); el integrante pone el PDF en `docs/arquitectura.pdf`. Después: commit en `feature/docs-s9`, Pull Request a `main`, merge, etiqueta `docs` y `git push --tags`; verificar que `.env` no esté en el repo.
2. **S10–11 · v0.1** — migraciones, modelos, factories y seeders. Rama `feature/modelo-datos` (desde `main`); se fusiona a `main` solo después de la etiqueta `docs`.
3. S12–13 · v0.2 — login con roles, dashboard y CRUD
4. S14 · v0.3 — API con Sanctum, Postman y URL pública
5. S15 · v0.4 — bot de WhatsApp conectado a la URL pública
6. S16 · v1.0 — sustentación en vivo individual (debe poder explicar y modificar cualquier parte)

## Entorno local (Windows)

- XAMPP con PHP 8.2.12, Composer, Node.js, Laravel 12. Proyectos en `C:\backend\` (nunca dentro de OneDrive).
- PHP no está en el PATH: usar `C:\xampp\php\php.exe artisan ...` hasta agregar `C:\xampp\php` a las variables de entorno.
- Laravel 13 exige PHP 8.3+, por eso se usa Laravel 12.
- MySQL de XAMPP, base `corteya` (utf8mb4_unicode_ci).
- Hosting (gratuito, sin tarjeta): **Render** para el backend (Docker, `backend/Dockerfile`) y el bot, y **Aiven** para MySQL 8.4 (plan Free). Laravel Cloud se descartó porque pide tarjeta. GitHub: `Alejo19092007/CorteYa`.
- Render gratis: sin terminal (los datos base se cargan solos al arrancar con `ProduccionSeeder`; la demo se carga desde el PC con `php artisan demo:cargar`), el servicio duerme tras 15 min sin visitas (~1 min en despertar) y las 750 horas al mes son por cuenta, no por servicio.
- Aiven: conexión cifrada con el certificado público `backend/certs/aiven-ca.pem` (`MYSQL_ATTR_SSL_CA`); la base exige llave primaria en todas las tablas; puede apagarse por inactividad.
- Los seeders de producción no usan Faker (no está en la imagen). Las contraseñas de las cuentas salen de `SEED_ADMIN_PASSWORD` y `SEED_BARBERO_PASSWORD` y son obligatorias en producción.
