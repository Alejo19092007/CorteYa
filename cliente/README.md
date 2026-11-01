# CorteYa · Bot de turnos

Aplicación Node.js + Express **independiente del backend Laravel**. Los clientes de la barbería usan un chat para ver los servicios, reservar, consultar y cancelar turnos. El bot **no tiene base de datos propia**: todo lo hace consumiendo la API REST pública de CorteYa con un token de Laravel Sanctum.

Canal activo: **Telegram** (`CANAL=telegram`). El adaptador de **WhatsApp Cloud API** también está construido y probado (`CANAL=whatsapp`); se dejó de usar porque Meta no envió el código de verificación para crear la cuenta de desarrollador.

## Cómo funciona

```
Cliente (Telegram) ──► adaptador del canal ──► conversación (máquina de estados) ──► cliente de la API ──► https://corteya.onrender.com/api
                          src/telegram/           src/bot/conversacion.js               src/api/corteyaClient.js
```

| Pieza | Qué hace |
|---|---|
| `src/api/corteyaClient.js` | Único punto que habla con la API. Inicia sesión (`POST /api/login`), guarda el token y lo manda en cada llamada. Si recibe **401** vuelve a iniciar sesión y repite la llamada una vez. Traduce los fallos: **sin conexión / tiempo agotado / 5xx**, **422** (una regla de negocio dijo que no) y **404**. |
| `src/bot/conversacion.js` | La conversación por pasos. No sabe nada de Telegram ni de HTTP: recibe `{ telefono, nombre, texto }` y devuelve los mensajes de respuesta. Por eso se prueba sin red y sirve para cualquier canal. |
| `src/telegram/` | Adaptador de Telegram: webhook (servidor) o polling (tu computador). |
| `src/whatsapp/`, `src/app.js` | Adaptador de WhatsApp Cloud API (webhook con firma HMAC). |
| `src/bot/formato.js` | Fechas en hora de Colombia (el servidor está en UTC), pesos, horas. |

**Uso de la API (requisito de la rúbrica):** lee con `GET /api/servicios`, `GET /api/barberos`, `GET /api/barberos/{id}/disponibilidad` y `GET /api/turnos?telefono=`; escribe con `POST /api/turnos` (reservar) y `DELETE /api/turnos/{id}` (cancelar). Los turnos reservados aparecen en el panel como **pendientes** y con el origen **Telegram** (el bot envía el campo `origen` según el canal activo), para que el dueño o el barbero los confirmen.

### Conversación

```
Menú:  1. Ver servicios   2. Reservar   3. Mis turnos   4. Cancelar
Reservar: servicio(s) → barbero → día (hoy / mañana / 05/11) → horario → confirmar (sí/no)
Cancelar: elegir turno → confirmar (la API aplica R3: hasta 2 h antes)
```

Escribir `menu` en cualquier momento vuelve al inicio.

### Cómo maneja los fallos

| Situación | Qué hace el bot |
|---|---|
| **Sin conexión** con la API (o Render dormido) | Responde "No pude comunicarme… intenta de nuevo en un minuto" y **conserva el paso**: el cliente repite el mensaje sin empezar de cero. La espera máxima por llamada es de 60 s porque el plan gratuito tarda en despertar. |
| **401** (token vencido o revocado) | Inicia sesión otra vez y repite la llamada; si sigue fallando avisa de un problema técnico. |
| **422** (regla de negocio: R1 horario ocupado, R3 menos de 2 h, R4 estado) | Muestra el mensaje de la API al cliente. Si el horario lo ganó otra persona, vuelve a ofrecer los horarios libres. |
| 404 | Avisa que el turno ya no existe. |

### Teléfono del cliente en Telegram

WhatsApp entrega el número de celular en cada mensaje; **Telegram no**. La API identifica al cliente por su teléfono, así que la primera vez el bot muestra el botón **📱 Compartir mi número**. Se verifica que el contacto sea el del propio usuario (`contact.user_id == from.id`) y que sea un celular colombiano (+57 3xx). El número queda asociado al chat **en memoria**: si el servidor se reinicia hay que tocar el botón de nuevo.

## Probar en tu computador

Requisitos: Node 20.6 o superior.

```bash
cd cliente
npm install
npm test            # ~120 pruebas, sin red
```

### A) Simulador (sin Telegram, para ver la conversación)

Crea `cliente/.env`:

```
MODO_SIMULADOR=true
CORTEYA_API_URL=https://corteya.onrender.com   # o http://127.0.0.1:8000 si corres el backend local
BOT_EMAIL=bot@corteya.com
BOT_PASSWORD=<contraseña>
```

```bash
npm run dev
```

Abre http://localhost:3000/simulador. El simulador **no existe** si `MODO_SIMULADOR` no es `true` y no se puede activar con `NODE_ENV=production`.

### B) Con Telegram real (modo polling, sin URL pública)

1. En Telegram habla con **@BotFather** → `/newbot` → elige nombre y usuario (debe terminar en `bot`). Te entrega el **token**.
2. Crea `cliente/.env` a partir de `.env.example` con `CANAL=telegram`, `TELEGRAM_MODO=polling`, `TELEGRAM_BOT_TOKEN`, `CORTEYA_API_URL`, `BOT_EMAIL` y `BOT_PASSWORD`.
3. `npm run dev` y escribe a tu bot en Telegram.

## Despliegue (Render, 2.º servicio web)

*New → Web Service →* el mismo repositorio, y:

| Campo | Valor |
|---|---|
| Root Directory | `cliente` |
| Runtime | `Docker` |
| Instance Type | `Free` |
| Health Check Path | `/health` |

Variables de entorno (en el panel de Render, **nunca** en el repositorio):

| Variable | Valor |
|---|---|
| `NODE_ENV` | `production` |
| `CORTEYA_API_URL` | `https://corteya.onrender.com` |
| `BOT_EMAIL` / `BOT_PASSWORD` | La cuenta de servicio del bot: `bot@corteya.com` y la contraseña que pusiste en `SEED_BOT_PASSWORD` del backend (no uses la cuenta del dueño) |
| `CANAL` | `telegram` |
| `TELEGRAM_BOT_TOKEN` | El token de @BotFather |
| `TELEGRAM_WEBHOOK_SECRET` | Un secreto inventado (16 a 256 caracteres: letras, números, `-`, `_`) |

La URL pública del bot se toma sola de `RENDER_EXTERNAL_URL`. Al arrancar, el bot **registra su propio webhook** en Telegram (`/telegram/webhook`); si no puede hacerlo, falla a la vista en los logs en vez de quedar "vivo" sin recibir nada. Telegram manda el secreto en la cabecera `X-Telegram-Bot-Api-Secret-Token` y el bot rechaza (401) todo lo que no lo traiga.

Imagen local: `docker build -t corteya-bot cliente`.

Plan gratuito: el servicio duerme tras 15 minutos sin mensajes; el primero después de ese rato puede tardar en responder mientras despierta (Telegram reintenta la entrega).

## Seguridad

- El token de Telegram, la contraseña del bot y los secretos viven solo en variables de entorno; `.env` está en `.gitignore`.
- Los errores y registros **nunca** incluyen tokens y los teléfonos aparecen enmascarados (`***4567`).
- Webhook de Telegram: secreto en cabecera, comparado en tiempo constante. Webhook de WhatsApp: firma `X-Hub-Signature-256` (HMAC-SHA256) con el secreto de la app.
- Las respuestas al webhook son inmediatas (200) y el trabajo sigue después; los mensajes repetidos se descartan y los de un mismo cliente se atienden en orden.
- Los datos que vienen de otras personas (nombres) se escapan antes de enviarse como HTML.
- En producción el bot exige `CORTEYA_API_URL` con `https://` y no localhost.
