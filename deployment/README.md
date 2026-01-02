# QA365 - Deployment Guide

Esta carpeta contiene los archivos de configuración necesarios para desplegar la aplicación en producción.

## 📁 Archivos Incluidos

### Configuración del Servidor

1. **nginx-config.conf**
   - Configuración de Nginx con SSL/HTTPS
   - Headers de seguridad
   - Optimizaciones de caché
   - Redirección HTTP → HTTPS

2. **qa365-queue-worker.service**
   - Servicio systemd para el worker de colas
   - Auto-reinicio en caso de fallo
   - Logging configurado

### Scripts de Automatización

3. **setup-server.sh**
   - Script de configuración inicial del servidor
   - Instala todas las dependencias
   - Configura MySQL, Redis, Nginx, PHP-FPM
   - **Ejecutar UNA SOLA VEZ** en servidor nuevo

4. **deploy.sh**
   - Script de despliegue automatizado
   - Hace backup automático
   - Actualiza código, dependencias y assets
   - Ejecuta migraciones
   - **Usar para actualizaciones** posteriores

## 🚀 Pasos de Despliegue

### 1. Preparar Servidor (Primera vez)

```bash
# Copiar archivos al servidor
scp -r deployment/ user@your-server:/tmp/

# Conectar al servidor
ssh user@your-server

# Ejecutar setup inicial (como root)
cd /tmp/deployment
sudo bash setup-server.sh
```

### 2. Clonar Aplicación

```bash
# Ir al directorio de la app
cd /var/www
sudo git clone https://github.com/your-repo/qa365.git qa365
cd qa365

# Dar permisos
sudo chown -R www-data:www-data /var/www/qa365
```

### 3. Configurar Ambiente

```bash
# Copiar .env de producción
sudo cp .env.production.example .env
sudo nano .env  # Editar con valores reales

# Generar key
sudo -u www-data php artisan key:generate

# Instalar dependencias
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci
sudo -u www-data npm run build
```

### 4. Base de Datos

```bash
# Ejecutar migraciones
sudo -u www-data php artisan migrate --force

# Seeders iniciales
sudo -u www-data php artisan db:seed --class=RoleSeeder
sudo -u www-data php artisan db:seed --class=PermissionSeeder

# Crear usuario admin (si no existe)
sudo -u www-data php artisan tinker
>>> User::create(['name' => 'Admin', 'email' => 'admin@qa365.com', 'password' => Hash::make('password')])->assignRole('admin');
```

### 5. Configurar SSL (Let's Encrypt)

```bash
# Instalar certbot (si no está instalado)
sudo apt install certbot python3-certbot-nginx

# Obtener certificado
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Auto-renovación (ya configurado automáticamente)
sudo certbot renew --dry-run
```

### 6. Iniciar Servicios

```bash
# Caché de configuración
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Iniciar queue worker
sudo systemctl start qa365-queue-worker
sudo systemctl enable qa365-queue-worker

# Verificar estado
sudo systemctl status qa365-queue-worker
```

### 7. Verificar Deployment

```bash
# Verificar Nginx
sudo nginx -t
sudo systemctl reload nginx

# Verificar PHP-FPM
sudo systemctl status php8.3-fpm

# Verificar logs
tail -f /var/log/nginx/qa365_error.log
tail -f /var/log/qa365-queue-worker.log
```

## 🔄 Actualizaciones Futuras

Para actualizar la aplicación después del deployment inicial:

```bash
cd /var/www/qa365
sudo bash deployment/deploy.sh
```

Este script automáticamente:
- Hace backup
- Actualiza código
- Instala dependencias
- Compila assets
- Ejecuta migraciones
- Limpia y regenera caché
- Reinicia servicios

## 🔧 Comandos Útiles

### Logs

```bash
# Ver logs de aplicación
tail -f storage/logs/laravel.log

# Ver logs de Nginx
tail -f /var/log/nginx/qa365_error.log

# Ver logs de queue worker
tail -f /var/log/qa365-queue-worker.log
```

### Mantenimiento

```bash
# Entrar en modo mantenimiento
php artisan down

# Salir de modo mantenimiento
php artisan up

# Limpiar caché
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Queue Worker

```bash
# Ver estado
sudo systemctl status qa365-queue-worker

# Reiniciar
sudo systemctl restart qa365-queue-worker

# Ver logs
journalctl -u qa365-queue-worker -f
```

## 📊 Monitoreo

### Health Check

La aplicación tiene un endpoint de health check en `/up`

```bash
curl https://your-domain.com/up
```

### Espacio en Disco

```bash
df -h
du -sh /var/www/qa365/storage/logs
```

### Procesos

```bash
ps aux | grep php
ps aux | grep nginx
```

## 🆘 Troubleshooting

### Error 500

```bash
# Revisar permisos
sudo chown -R www-data:www-data /var/www/qa365
sudo chmod -R 775 /var/www/qa365/storage
sudo chmod -R 775 /var/www/qa365/bootstrap/cache

# Ver logs
tail -f storage/logs/laravel.log
```

### Queue Worker no funciona

```bash
# Verificar Redis
redis-cli ping

# Reiniciar worker
sudo systemctl restart qa365-queue-worker

# Ver errores
journalctl -u qa365-queue-worker -n 50
```

### Slow Performance

```bash
# Verificar caché está activo
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Verificar Redis
redis-cli INFO
```

## 📞 Soporte

Para problemas o preguntas, revisar:
- Logs de aplicación: `storage/logs/laravel.log`
- Logs de Nginx: `/var/log/nginx/qa365_error.log`
- Logs de sistema: `journalctl -xe`
