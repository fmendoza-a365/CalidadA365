#!/bin/bash

# Detener el script si ocurre algún error
set -e

echo "🚀 Iniciando despliegue local de QA365..."

# 1. Verificar y preparar el archivo .env
if [ ! -f .env ]; then
    echo "📄 Archivo .env no encontrado. Copiando de .env.example..."
    cp .env.example .env
    echo "🔑 Generando APP_KEY..."
    php artisan key:generate
fi

# 2. Instalar dependencias
echo "📦 Instalando dependencias de PHP (Composer)..."
composer install

echo "📦 Instalando dependencias de Node.js (NPM)..."
npm install

# 3. Base de Datos SQLite
echo "🗄️ Verificando base de datos SQLite..."
mkdir -p database
touch database/database.sqlite

# 4. Migraciones y Seeders (Para levantar todo con la data de prueba)
echo "🌱 Recreando base de datos y ejecutando seeders base..."
php artisan migrate:fresh --seed

echo "🏢 Cargando campañas, usuarios y fichas de demostración (Demo y Alfin)..."
php artisan db:seed --class=DemoDataSeeder
php artisan db:seed --class=AlfinDemoSeeder

# 5. Limpiar Cachés
echo "🧹 Limpiando cachés de la aplicación..."
php artisan optimize:clear

# 6. Levantar Servidores Simultáneamente
echo "🌟 Levantando todos los servicios..."
echo "👉 Esto iniciará: Servidor PHP, Frontend (Vite), Workers de Colas y Logs."

# Aprovechamos el script "dev" que ya tienes en tu composer.json que usa concurrently
composer run dev
