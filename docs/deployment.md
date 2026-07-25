# Deployment

Stack: **Laravel (Livewire) + Reverb** on **Render**, **Neon** (Postgres) as the
database. Three moving parts run in production; a fourth (the scheduler) is a cron.

## Services

| Service | What it runs | Notes |
|---|---|---|
| **Web** (Render Web Service) | the Laravel app (HTTP + Livewire) | Serves pages and Livewire updates. |
| **Reverb worker** (Render Background Worker) | `php artisan reverb:start` | Persistent WebSocket server for realtime bidding. Must be always-on. |
| **Scheduler** (cron) | `php artisan schedule:run` every minute | Drives `sessions:expire` (daily). See below. |
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

## Build & release

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # builds front-end incl. Echo (needs VITE_REVERB_* set)
php artisan config:cache route:cache view:cache
```

### Database schema
The domain schema is hand-authored SQL (R5). Apply once to Neon, in order:

```bash
php artisan migrate --force                      # framework tables (users, sessions, cache, jobs)
psql "$DATABASE_URL" -f db/migrations/001_init.sql
psql "$DATABASE_URL" -f db/migrations/002_add_weight_fractions.sql
# then any later db/migrations/NNN_*.sql in order
```
Do **not** use `php artisan migrate` for the domain tables — they live only in `db/`.

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
invite links (7-day rule). It's registered to run daily; production needs the
Laravel scheduler ticking once a minute:

```
* * * * * cd /app && php artisan schedule:run >> /dev/null 2>&1
```
On Render, use a Cron Job service running `php artisan schedule:run`, or a
dedicated worker running `php artisan schedule:work`.

## Post-deploy checklist

- [ ] `php artisan db:show` connects to Neon.
- [ ] `php artisan schedule:list` shows `sessions:expire`.
- [ ] Reverb worker is up; a two-browser bid updates instantly.
- [ ] `APP_DEBUG=false`, caches built.
