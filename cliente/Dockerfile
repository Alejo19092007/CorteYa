# Imagen del bot de CorteYa para Render (Node 22 LTS, sin dependencias de desarrollo).
FROM node:22-alpine

WORKDIR /app
ENV NODE_ENV=production

# Primero solo las dependencias: Docker reutiliza esta capa mientras package-lock.json no cambie
COPY package.json package-lock.json ./
RUN npm ci --omit=dev --no-audit --no-fund

COPY src ./src

# No se ejecuta como root
USER node

# Render define PORT (10000 por defecto); el bot lo lee de la variable de entorno
EXPOSE 10000
CMD ["node", "src/server.js"]
