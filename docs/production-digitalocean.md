# QA365 Production On DigitalOcean

This project is prepared for a low-cost DigitalOcean Droplet deployment.

## Recommended first server

- Droplet: Basic or Premium AMD/Intel, 2 vCPU / 4 GB RAM / 80 GB SSD
- OS: Ubuntu 24.04 LTS
- App runtime: Nginx, PHP 8.3 FPM, Supervisor, FFmpeg/ffprobe
- Database: PostgreSQL on the Droplet for the first stage
- Queue/cache: Redis on the Droplet for the first stage
- File storage: DigitalOcean Spaces, private bucket

For higher volume, move PostgreSQL and Redis to DigitalOcean Managed Databases before scaling the Droplet.

## Lowest practical cost

Use 1 vCPU / 2 GB RAM only for a pilot with low upload volume, one AI worker and strict use of DigitalOcean Spaces for audio files. It can work, but queue latency will grow while transcribing/evaluating audio and deploy builds may use swap.

For first real production, use 2 vCPU / 4 GB RAM. It gives enough headroom for Nginx, PHP-FPM, PostgreSQL, Redis, Supervisor workers, Composer and Vite builds without moving the database to a managed service on day one.

If audio volume grows, scale in this order:

1. Move audio to Spaces if it is not already there.
2. Increase AI workers or move them to a second worker Droplet.
3. Move PostgreSQL to a managed database.
4. Upgrade the web Droplet.

## First setup

```bash
sudo bash deployment/digitalocean/setup-droplet.sh
```

The setup script creates a swap file by default. Override its size when needed:

```bash
sudo SWAP_SIZE=4G bash deployment/digitalocean/setup-droplet.sh
```

Create PostgreSQL objects:

```bash
sudo -u postgres psql
CREATE DATABASE qa365;
CREATE USER qa365 WITH ENCRYPTED PASSWORD 'change-this-password';
GRANT ALL PRIVILEGES ON DATABASE qa365 TO qa365;
\q
```

Clone the repository into `/var/www/qa365`, create `.env` from `.env.production.example`, set `APP_KEY`, database credentials, Spaces credentials and AI provider keys.

```bash
cd /var/www/qa365
cp .env.production.example .env
php artisan key:generate
sudo APP_DIR=/var/www/qa365 BRANCH=main bash deployment/digitalocean/deploy.sh
```

## SSL

After the DNS A record points to the Droplet:

```bash
sudo certbot --nginx -d qa365.com.pe -d www.qa365.com.pe
```

Then set `APP_URL=https://qa365.com.pe` and redeploy.

## Workers

Supervisor starts two worker groups:

- `qa365-default-worker`: default and notifications.
- `qa365-ai-worker`: transcription, ai-scoring and insights.

The Redis retry window must be greater than the longest worker timeout. Keep `REDIS_QUEUE_RETRY_AFTER=900` or higher for long audio jobs.

## QA evaluation flow

1. Audio upload creates an interaction and queues transcription.
2. Completed audio transcription queues AI scoring automatically when a published quality form exists.
3. Text transcript upload queues AI scoring immediately when a published quality form exists.
4. AI scoring creates a draft result with `pending_monitor_review`.
5. QA monitor approves and publishes, reanalyzes with AI, or creates a final manual correction.
6. The advisor sees only published evaluations and can accept or dispute them.
7. Supervisors see their team's published evaluations for coaching, but they are not a required approval step.
8. Disputes escalate by exception: supervisor comment, QA monitor analysis, QA coordinator validation, QA manager final resolution.

## Operational context in quality forms

Each quality form can store operational context for AI scoring:

- Markdown text for products, prices, campaign rules and exact or near-exact scripts.
- Optional PDF, Markdown or TXT file up to 10 MB.
- The extracted text is added to the AI prompt when evaluating calls with that quality form.

The context edit action is available only to users with `edit_quality_forms`. In the default roles, that includes `admin`, `qa_manager`, `manager` and `qa_coordinator`. QA monitors can view the quality form and run evaluations, but they do not edit the source criteria/context unless you explicitly grant that permission from Roles y Permisos.

## Deploy updates

Always deploy with the repository script, not by copying files manually:

```bash
cd /var/www/qa365
sudo APP_DIR=/var/www/qa365 BRANCH=main bash deployment/digitalocean/deploy.sh
```

The deploy script now clears Laravel caches, resets the permission cache, runs migrations, rebuilds Vite assets, validates the operational-context routes and restarts PHP-FPM so OPcache cannot serve old code.

By default the deploy script creates a compressed database backup under `storage/app/backups/releases` before `php artisan migrate --force`. Do not disable this for production. To use another backup path:

```bash
sudo BACKUP_DIR=/var/backups/qa365 APP_DIR=/var/www/qa365 BRANCH=main bash deployment/digitalocean/deploy.sh
```

This deployment path updates code and schema without truncating the existing production database. It must not be replaced with `migrate:fresh`, manual table drops, or a new empty `.env` pointed at the live database.

If the "Agregar Contexto" or "Editar Contexto" actions do not appear after deploying, run these checks on the Droplet:

```bash
cd /var/www/qa365
git log -1 --oneline
php artisan migrate:status | grep operational_context
php artisan route:list --name=quality-forms.update-context
php artisan route:list --name=quality-forms.context.download
php artisan optimize:clear
php artisan permission:cache-reset
sudo systemctl restart php8.3-fpm
sudo supervisorctl restart qa365-default-worker:* qa365-ai-worker:*
```

Then confirm you are logged in with a role that has `edit_quality_forms`.

## Production checklist

- `APP_ENV=production`
- `APP_DEBUG=false`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_ENCRYPT=true`
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `FILESYSTEM_DISK=s3`
- Private Spaces bucket
- Daily database backups or Droplet snapshots
- Release backup created before migrations
- `FFPROBE_PATH=/usr/bin/ffprobe`
- `composer audit --locked --no-dev`
- `npm audit --omit=dev`
- `php artisan test`
- `npm run build`
