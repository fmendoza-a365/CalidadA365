# QA365 Production On DigitalOcean

This project is prepared for a low-cost DigitalOcean Droplet deployment.

## Recommended first server

- Droplet: Basic or Premium AMD/Intel, 2 vCPU / 4 GB RAM / 80 GB SSD
- OS: Ubuntu 24.04 LTS
- App runtime: Nginx, PHP 8.3 FPM, Supervisor
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
- `composer audit --locked --no-dev`
- `npm audit --omit=dev`
- `php artisan test`
- `npm run build`
