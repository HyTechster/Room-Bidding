# Deployment (v2 — single-operator)

Stack: **Laravel (Livewire)** on a **Render Web Service** (Docker / FrankenPHP)
with **Neon** (Postgres). After the v2 pivot there is **one** service — no Reverb,
no cron.

## Render setup

Render has no native PHP runtime, so the app ships as a **Docker image**
(`Dockerfile` at the repo root, FrankenPHP — a production server). Create **one
Web Service (Docker)** from the repo. Its start command is the image default
(FrankenPHP); no per-service command needed.

**Pre-Deploy Command** (runs once per release, DB reachable):
```bash
php artisan migrate --force && php artisan db:apply && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## Database (Neon)

Framework tables come from `artisan migrate`; the product schema is hand-authored
SQL in `db/migrations/*.sql` (R5), applied by a tracking-aware command so it's safe
on every deploy:

```bash
php artisan migrate --force     # framework tables (users, sessions, cache, jobs)
php artisan db:apply            # domain tables — applies only new db/migrations/*.sql
```

- On a database whose schema was already applied by hand, run
  `php artisan db:apply --mark-only` **once**, then plain `db:apply` afterwards.
- Do **not** use `php artisan migrate` for the domain tables — they live only in `db/`.

## Environment (web service)

- `APP_KEY` — `php artisan key:generate`
- `APP_ENV=production`, `APP_DEBUG=false`
- `APP_URL=https://<your-app>.onrender.com` **and** `ASSET_URL=<same>` — required so
  CSS/JS load over HTTPS (behind Render's proxy). `bootstrap/app.php` already
  trusts the proxy (`trustProxies(at: '*')`).
- **Database (Neon):** `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT=5432`,
  `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE=require`
- `SESSION_DRIVER=database` (survives restarts — keeps users logged in)
- `BROADCAST_CONNECTION=log` (realtime removed)

## Build (handled by the Dockerfile)

```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build     # front-end assets (Tailwind)
```
The Dockerfile builds assets in a Node stage and serves via FrankenPHP. It also
strips the `frankenphp` binary's file capabilities so it runs under Render's
restricted runtime.

## Notes

- **Sleep:** Render free tier sleeps after ~15 min and auto-wakes (~30–60s) on the
  next request; a paid instance (~$7/mo) stays up. Neon suspends on inactivity and
  wakes on the next connection (~1s).
- **No cron, no Reverb:** results are saved as `ended` sessions and kept
  permanently; nothing needs a background process.

## Post-deploy checklist

- [ ] `php artisan db:show` connects to Neon.
- [ ] `php artisan db:apply` reports the domain tables applied (or `--mark-only` once).
- [ ] Homepage `/` loads with styling (confirms `APP_URL`/assets).
- [ ] `/tool` runs a split as a guest; logging in enables **Save**.
- [ ] `APP_DEBUG=false`, caches built.
