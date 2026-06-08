# Deploy Fase 1 + Fase2 — Guía Paso a Paso

**Fecha:** 2026-06-08
**Cambios:** Permisos de seguridad, scoring unificado, locks idempotentes, queries consolidadas, geo-data, font dedup, sleep→block
**Riesgo:** MEDIO — toca rutas, scoring y workers

---

## Pre-Deploy: Checklist

- [ ] Tener acceso SSH al droplet (`root@159.203.184.93`)
- [ ] Confirmar que no hay uploads de audio en proceso
- [ ] Confirmar que los workers están RUNNING
- [ ] Tener una ventana de baja carga (idealmente fuera de horario laboral Perú)

---

## Paso 1: Backup de base de datos

```bash
ssh root@159.203.184.93

cd /var/www/qa365
mkdir -p storage/app/backups/pre-deploy

# Backup PostgreSQL
sudo -u postgres pg_dump qa365 | gzip > storage/app/backups/pre-deploy/qa365_$(date +%Y%m%d_%H%M%S).sql.gz

# Verificar que el backup se creó
ls -la storage/app/backups/pre-deploy/
```

**Si algo sale mal, restaurar con:**
```bash
gunzip < storage/app/backups/pre-deploy/qa365_XXXXXX.sql.gz | sudo -u postgres psql qa365
```

---

## Paso 2: Poner la app en mantenimiento

```bash
cd /var/www/qa365
php artisan down --retry=60
```

---

## Paso 3: Crear los 14 permisos nuevos en la base de datos

**Esto se ANTES de desplegar el código.** Los permisos deben existir en la BD antes de que las rutas los requieran.

```bash
cd /var/www/qa365

php artisan tinker --execute="
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Crear permisos nuevos
\$newPerms = [
    'export_calibration',
    'export_evaluation_audit',
    'export_evaluations',
    'manage_evaluation_lifecycle',
    'manage_retention',
    'manage_sampling',
    'manage_staffing',
    'respond_evaluations',
    'resolve_disputes',
    'review_disputes',
    'view_ai_performance',
    'view_sampling',
    'view_staffing',
    'view_work_queue',
];

foreach (\$newPerms as \$perm) {
    Permission::firstOrCreate(['name' => \$perm, 'guard_name' => 'web']);
    echo \"Created: \$perm\n\";
}

// Asignar permisos a roles existentes
\$rolePerms = [
    'agent' => ['respond_evaluations'],
    'supervisor' => ['review_disputes', 'view_sampling', 'view_staffing', 'view_work_queue', 'manage_evaluation_lifecycle', 'export_evaluations'],
    'manager' => ['manage_evaluation_lifecycle', 'review_disputes', 'resolve_disputes', 'view_work_queue', 'view_sampling', 'view_staffing', 'export_evaluations', 'export_calibration', 'export_evaluation_audit'],
    'qa_monitor' => ['manage_evaluation_lifecycle', 'review_disputes', 'manage_sampling', 'manage_staffing', 'view_work_queue', 'view_sampling', 'view_staffing', 'export_evaluations', 'export_calibration', 'export_evaluation_audit'],
    'qa_coordinator' => ['manage_evaluation_lifecycle', 'review_disputes', 'resolve_disputes', 'manage_sampling', 'manage_staffing', 'view_work_queue', 'view_sampling', 'view_staffing', 'export_evaluations', 'export_calibration', 'export_evaluation_audit'],
    'qa_manager' => ['manage_evaluation_lifecycle', 'review_disputes', 'resolve_disputes', 'manage_sampling', 'manage_staffing', 'view_work_queue', 'view_sampling', 'view_staffing', 'view_ai_performance', 'export_evaluations', 'export_calibration', 'export_evaluation_audit'],
];

foreach (\$rolePerms as \$roleName => \$perms) {
    \$role = Role::where('name', \$roleName)->first();
    if (\$role) {
        foreach (\$perms as \$perm) {
            \$role->givePermissionTo(\$perm);
        }
        echo \"Role \$roleName: assigned \" . count(\$perms) . \" permissions\n\";
    } else {
        echo \"WARNING: Role \$roleName not found!\n\";
    }
}

// Admin ya tiene todos por syncPermissions, verificar
\$admin = Role::where('name', 'admin')->first();
if (\$admin) {
    \$admin->syncPermissions(Permission::all());
    echo 'Admin: synced ' . \$admin->permissions->count() . ' permissions';
}
"
```

**Verificar:**
```bash
php artisan tinker --execute="echo 'Total permissions: ' . \Spatie\Permission\Models\Permission::count();"
```

Debe decir `Total permissions: 50`.

---

## Paso 4: Desplegar el código

```bash
cd /var/www/qa365

# Fetch y reset al último commit
git fetch origin main
git reset --hard origin/main

# Instalar dependencias PHP
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Build de assets frontend
npm ci
npm run build

# Limpiar caches
php artisan optimize:clear

# Migraciones (no hay nuevas en este deploy, pero por seguridad)
php artisan migrate --force

# Cachear configuración y rutas
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Resetear cache de permisos de Spatie
php artisan permission:cache-reset
```

---

## Paso 5: Verificar rutas

```bash
# Verificar que las rutas nuevas tienen middleware de permisos
php artisan route:list --json | grep -c "permission:"
# Debería ser mayor que antes (22+ rutas nuevas con permisos)

# Verificar rutas específicas
php artisan route:list --name=evaluations.publish --json
php artisan route:list --name=disputes.resolve --json
php artisan route:list --name=sampling.store --json
```

---

## Paso 6: Permisos de archivos

```bash
chown -R www-data:www-data storage bootstrap/cache public/build public/data
chmod -R ug+rwX storage bootstrap/cache
```

---

## Paso 7: Reiniciar servicios

```bash
# Workers de Supervisor
supervisorctl reread
supervisorctl update
supervisorctl restart qa365-default-worker:*
supervisorctl restart qa365-transcription-worker:*
supervisorctl restart qa365-ai-scoring-worker:*

# PHP-FPM
systemctl restart php8.3-fpm

# Nginx (reload, no restart)
systemctl reload nginx
```

---

## Paso 8: Sacar de mantenimiento

```bash
cd /var/www/qa365
php artisan up
```

---

## Paso 9: Verificación post-deploy

```bash
# Health check
php artisan qa:health --json

# Verificar que la app responde
curl -s -o /dev/null -w "%{http_code}" https://qa365.com.pe/up
# Debe responder 200

# Verificar que el login funciona
curl -s -o /dev/null -w "%{http_code}" https://qa365.com.pe/login
# Debe responder 200

# Verificar permisos
php artisan tinker --execute="echo 'Permissions: ' . \Spatie\Permission\Models\Permission::count();"

# Verificar workers
supervisorctl status

# Logs recientes (no debe haber errores nuevos)
tail -20 storage/logs/laravel.log
```

---

## Paso 10: Monitoreo (primeros 30 minutos)

```bash
# Monitorear logs en tiempo real
tail -f storage/logs/laravel.log

# En otra terminal, verificar workers
watch -n 10 supervisorctl status

# Verificar queue jobs
watch -n 10 "php artisan queue:failed | wc -l"
```

---

## Rollback (si algo sale mal)

### Opción A: Rollback de código (mantener BD)
```bash
cd /var/www/qa365
php artisan down
git reset --hard HEAD~1  # Volver al commit anterior
composer install --no-dev
npm ci && npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan up
```

### Opción B: Rollback completo (restaurar BD)
```bash
cd /var/www/qa365
php artisan down

# Restaurar backup
gunzip < storage/app/backups/pre-deploy/qa365_XXXXXX.sql.gz | sudo -u postgres psql qa365

# Restaurar código
git reset --hard HEAD~1
composer install --no-dev
npm ci && npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan permission:cache-reset
php artisan up
```

---

## Resumen de lo que cambia

| Componente | Antes | Después |
|------------|-------|---------|
| Permisos en BD | 36 | 50 |
| Rutas protegidas | ~40 | ~62 (22 nuevas) |
| Scoring `not_found` | 0.0 (manual) / 0.5 (AI) | 0.5 (ambos) |
| TranscribeAudioJob | Sin lock | Con Cache::lock |
| Dashboard queries | 7 queries | 4 queries |
| Gemini cache lock | sleep(3) | block(3) |
| Geo-data | Inline 252 líneas | Archivo JS externo |
| Font loading | Duplicado | Una sola carga |
