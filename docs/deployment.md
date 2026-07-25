# Deployment

Stack: **Laravel (Livewire) + Reverb** on **Render**, **Neon** (Postgres) as the
database. Three moving parts run in production; a fourth (the scheduler) is a cron.

## Services

| Service | What it runs | Notes |
|---|---|---|
| **Web** (Render Web Service) | the Laravel app (HTTP + Livewire), FrankenPHP | Serves pages and Livewire updates. |
| **Reverb** (Render Web Service) | `php artisan reverb:start` | Persistent WebSocket server; needs a public URL for `wss://`. Always-on. |
| **Cron** (Render Cron Job) | `php artisan sessions:expire` (daily) | Lifecycle cleanup. |
| **Database** | Neon Postgres | Reached via the connection string; pooler (6543) under load. |

> Realtime degrades gracefully: if the Reverb worker is down, the UI still updates
> via the 10s polling fallback, and user actions never fail (broadcasts are wrapped
> in `rescue()`).

## Sleep / pause behaviour

| Component | Idle behaviour | Recovery | Mitigation |
|---|---|---|---|
| Render web (free) | Sleeps after ~15 min | **Auto-wakes** on next request (~30–60s) | Only hits the first request; live sessions stay warm. Paid ~$7/mo = no sleep. |
| Render Reverb worker | Not suitable on free tier (needs to stay up) | — | Use a **paid worker** for live sessions; free tier only for local/dev. |
| Neon (free) | Suspends after inactivity | Wakes on next connection (~1s) | Faster than Supabase; keep-alive via any weekly traffic, or a paid plan. |

## Environment

Copy `.env.example` → `.env` and set:

- `APP_KEY` — `php artisan key:generate`
- `APP_URL` — the public web URL; `APP_ENV=production`, `APP_DEBUG=false`
- **Database** (Neon): `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT=5432`,
  `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE=require`
- **Broadcasting / Reverb**: `BROADCAST_CONNECTION=reverb`, and
  `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` (share the same values
  between the web and worker services), `REVERB_HOST` (public ws host),
  `REVERB_PORT`, `REVERB_SCHEME=https` in production, plus the `VITE_REVERB_*`
  mirror vars (baked into the front-end at build time).
- **Billing** (sandbox until a provider is wired — Q2):
  `SESSION_PRICE_CENTS`, `BILLING_CURRENCY=MYR`, `PAYMENT_PROVIDER=stub`.
- `SESSION_DRIVER=database` (survives instance restarts — enables host resume).

## Render (Docker) setup

Render has no native PHP runtime, so the app ships as a **Docker image**
(`Dockerfile` at the repo root, FrankenPHP — a production server, not `artisan
serve`). Build the front-end `VITE_REVERB_*` values in as build args.

Create three Render services from the **same repo/image**:

| Service | Type | Command | Notes |
|---|---|---|---|
| **web** | Web Service (Docker) | *(image default: FrankenPHP)* | Serves HTTP + Livewire. |
| **reverb** | Web Service (Docker) | `php artisan reverb:start --host 0.0.0.0 --port $PORT` | Needs a public URL for `wss://`; point the web app's `REVERB_HOST` at it. |
| **cron** | Cron Job (Docker) | `php artisan sessions:expire` | Schedule daily. |

**Pre-Deploy Command** (on the web service — runs once per release, DB reachable):
```bash
php artisan migrate --force && php artisan db:apply && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

If you're not using Docker (an image that already has PHP + Node), the equivalent
**Build Command** is:
```bash
composer install --no-dev --optimize-autoloader && npm ci && npm run build && php artisan config:cache && php artisan route:cache && php artisan view:cache
```
and the **Start Command** for web is the FrankenPHP/php-fpm server (avoid
`php artisan serve` for real traffic).

## Database schema

Framework tables come from `artisan migrate`; the domain schema is hand-authored
SQL in `db/migrations/*.sql` (R5), applied by a portable command that tracks what
has run (`domain_migrations` table) so it's safe on every deploy:

```bash
php artisan migrate --force     # framework tables (users, sessions, cache, jobs)
php artisan db:apply            # domain tables — applies only new db/migrations/*.sql
```

- On a database whose schema was already applied by hand, run
  `php artisan db:apply --mark-only` **once** to record the existing files without
  re-running them; afterwards plain `db:apply` only applies new ones.
- Do **not** use `php artisan migrate` for the domain tables — they live only in `db/`.

## Reverb worker

Runs as a separate Render Background Worker:
```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```
Set `REVERB_SERVER_HOST=0.0.0.0` and `REVERB_SERVER_PORT` accordingly. The public
web-facing `REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` must point at how browsers
reach the worker (typically behind TLS on 443).

## Scheduler (lifecycle cleanup)

The `sessions:expire` command expires stale unfinished sessions and revokes their
invite links (7-day rule).

- **On Render (simplest):** a **Cron Job** service running `php artisan sessions:expire`
  directly, scheduled daily.
- **Generic host:** run the Laravel scheduler once a minute (it invokes the command
  on its daily cadence, defined in `routes/console.php`):
  ```
  * * * * * cd /app && php artisan schedule:run >> /dev/null 2>&1
  ```

## Post-deploy checklist

- [ ] `php artisan db:show` connects to Neon.
- [ ] `php artisan db:apply` reports the domain tables applied (or `--mark-only` run once on a pre-existing DB).
- [ ] `php artisan schedule:list` shows `sessions:expire` (or a Render Cron Job runs it).
- [ ] Reverb service is up; a two-browser bid updates instantly.
- [ ] `APP_DEBUG=false`, caches built.
