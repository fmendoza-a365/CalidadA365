# QA365 - Quality Assurance Platform

Sistema de gestión de calidad para call centers con análisis impulsado por IA.

## 🚀 Despliegue en Render

### Opción 1: Blueprint Automático (Recomendado)

1. Ve a [Render Dashboard](https://dashboard.render.com)
2. Click en **"New"** → **"Blueprint"**
3. Conecta tu repositorio: `fmendoza-a365/CalidadA365`
4. Render detectará automáticamente `render.yaml`
5. Click en **"Apply"**

Render creará automáticamente:
- ✅ Servicio Web (Laravel + Nginx + PHP 8.3)
- ✅ Base de datos PostgreSQL
- ✅ Servicio Redis (caché y colas)

### Opción 2: Manual

Si prefieres configurar manualmente:

1. **Crear Base de Datos PostgreSQL**
   - New → PostgreSQL
   - Name: `qa365-db`
   - Plan: Starter

2. **Crear Redis**
   - New → Redis
   - Name: `qa365-redis`
   - Plan: Starter

3. **Crear Web Service**
   - New → Web Service
   - Environment: Docker
   - Connect repository
   - Build Command: (automático desde Dockerfile)
   - Start Command: (automático desde Dockerfile)

4. **Variables de Entorno**
   ```
   APP_KEY=<generar con: php artisan key:generate --show>
   APP_ENV=production
   APP_DEBUG=false
   DATABASE_URL=<copiar de PostgreSQL>
   REDIS_URL=<copiar de Redis>
   AI_PROVIDER=gemini
   AI_GEMINI_API_KEY=<tu-api-key>
   ```

### Configuración Post-Deploy

1. **Crear Usuario Admin**
   - Ir a Shell en Render
   - Ejecutar:
   ```bash
   php artisan tinker
   $user = User::create(['name' => 'Admin', 'email' => 'admin@qa365.com', 'password' => Hash::make('tu-password')]);
   $user->assignRole('admin');
   ```

2. **Verificar Deployment**
   - Visitar: `https://tu-app.onrender.com/up`
   - Login con credenciales de admin

## 🏗️ Arquitectura

- **Backend:** Laravel 11 + PHP 8.3
- **Frontend:** Blade + Alpine.js + Tailwind CSS
- **Base de Datos:** PostgreSQL (producción) / SQLite (desarrollo)
- **Caché/Colas:** Redis
- **IA:** Gemini 2.0 Flash / OpenAI / Claude

## 📦 Instalación Local

```bash
# Clonar repositorio
git clone https://github.com/fmendoza-a365/CalidadA365.git
cd CalidadA365

# Instalar dependencias
composer install
npm install

# Configurar ambiente
cp .env.example .env
php artisan key:generate

# Base de datos
touch database/database.sqlite
php artisan migrate
php artisan db:seed

# Compilar assets
npm run dev

# Servidor local
php artisan serve
```

## 🔑 Características Principales

- ✅ Evaluación de calidad con IA (Gemini/OpenAI/Claude)
- ✅ Gestión de fichas de calidad personalizables
- ✅ Dashboard customizable tipo BI
- ✅ Sistema de notificaciones en tiempo real
- ✅ Análisis de insights con IA
- ✅ Gestión de disputas de agentes
- ✅ Roles y permisos granulares
- ✅ Optimizado para producción (índices, caché, rate limiting)

## 📊 Roles del Sistema

- **Admin:** Acceso completo
- **QA Manager:** Gestión de calidad y reportes
- **Supervisor:** Visualización de evaluaciones y equipos
- **Agent:** Vista de evaluaciones propias y disputas

## 🛠️ Stack Tecnológico

- Laravel 11
- PHP 8.3
- PostgreSQL
- Redis
- Tailwind CSS
- Alpine.js
- Chart.js
- Vite

## 📝 Licencia

Propietario - A365 Contact Center Solutions

## 🤝 Soporte

Para soporte técnico, contactar a: fmendoza@a365.com
