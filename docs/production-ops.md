# Production Operations Checklist

Use this checklist for each production release.

## Deploy

1. Put the application in maintenance mode if the release includes migrations.
2. Pull the release.
3. Run `composer install --no-dev --optimize-autoloader`.
4. Create a database backup before migrations.
5. Run `npm ci` and `npm run build` if assets are built on the server.
6. Run `php artisan migrate --force`.
7. Run `php artisan optimize:clear` and `php artisan optimize`.
8. Restart queue workers with `php artisan queue:restart`.
9. Bring the application back up.

Never use `php artisan migrate:fresh`, `db:wipe`, or manual table drops against production. The DigitalOcean deploy script creates a compressed database backup automatically before migrations.

## Required Workers

Run workers for at least these queues:

- `ai-scoring`
- `transcription`
- `default`

Recommended command shape:

```bash
php artisan queue:work redis --queue=transcription,ai-scoring,default --tries=3 --timeout=900
```

Local development can use `database` as the queue driver, but production should use Redis and Supervisor.

## Scheduler

Ensure cron runs Laravel's scheduler every minute:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Health Checks

Run:

```bash
php artisan qa:health --json
```

Alert if `status` is not `ok`, or if `jobs_pending`, `ai_failed`, or `monitor_pending` grows unexpectedly.

## Data Hygiene

- Review failed jobs daily.
- Back up the database before migrations.
- Back up private storage containing audios and transcripts.
- Define retention rules for audios/transcripts according to client policy.
- Use `php artisan qa:normalize-evaluation-statuses` before any legacy-status cleanup, then rerun with `--apply` only after reviewing the counts.
- Review media retention with `php artisan qa:prune-private-media --days=365`.
- Apply media retention only after backup: `php artisan qa:prune-private-media --days=365 --redact-transcripts --apply`.
