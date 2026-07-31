<p align="center">
  <img src="public/favicon.svg" width="88" alt="Room Bidding logo">
</p>

<h1 align="center">Room Bidding</h1>

<p align="center">Fair rent splitting for shared houses. Each room is priced by demand, and the amounts always add up to <strong>exactly</strong> the total rent.</p>

---

## What it is

Splitting rent equally isn't fair when rooms differ. **Room Bidding** runs a simple,
demand-based mechanism: over-subscribed rooms get more expensive, quieter rooms get
cheaper, round by round, until everyone fits. The result is a per-person breakdown
that is transparent, printable, and provably sums to the rent.

It's a **single-operator** tool: one person sets up the house, places everyone into
rooms (tap or drag), and settles. No accounts are required to use it; signing in only
adds the ability to **save** results and revisit them.

## How it works

1. **Set up** the total rent, the currency, the rooms and their capacities, and
   everyone's name.
2. **Place people** into rooms. Tap a name then a room, or drag on desktop.
3. **Next** recalculates prices for the current placement. If a room is over
   capacity, weights evolve and prices adjust. Repeat until no room is over capacity.
4. **Finish** to see who pays what. Every per-person amount is exact and the total
   matches the rent to the sen.

The pricing math is a two-layer model (desirability *weights* → derived *prices*)
with two guarantees baked in structurally:

- **Budget balance:** the sum of all payments equals the rent exactly, every round.
- **Symmetry:** a price increase and an equal decrease are exact mirror images.

The engine uses exact rational arithmetic (no binary floats for money) and is covered
by a thorough unit-test suite.

## Features

- Demand-based, multi-round room pricing with a manual **Next / Finish** flow
- Live occupancy, colour status (full / space / over capacity), and per-room prices
- Frozen prices while placing, recalculated only on **Next** (with previous-price and
  "in demand / discount" explanations)
- Currency selector (RM default, plus common currencies); percentage or fixed offset
- Guest use with nothing saved; optional sign-in to **save results** and keep a history
- Permanent, shareable **result pages** with a round-by-round audit and **print / PDF**
- Mobile-friendly (tap-to-place), accessible status cues (icon + text, not colour only)

## Tech stack

- **Laravel** (PHP) + **Livewire** for a server-rendered, interactive UI
- **Blade + Tailwind CSS** (Vite build)
- **PostgreSQL** (hosted on **Neon**) for saved results; schema is hand-authored SQL in `db/`
- A pure, framework-independent **pricing engine** in `app/Domain/Pricing`
- **FrankenPHP** in Docker for production; deployed on **Render**

## Getting started (local)

Requirements: PHP 8.4 (with `pdo_pgsql`, `bcmath`, `mbstring`, …), Composer, Node 20+.

```bash
composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate
# point DB_* at your Postgres/Neon instance (or use sqlite for a quick spin)

php artisan migrate            # framework tables
php artisan db:apply           # domain tables (db/migrations/*.sql)

php artisan serve              # http://127.0.0.1:8000
```

Open the homepage and click **Launch the tool**. No login needed to run a split.

## Testing

The pricing engine is pure PHP and fully unit-tested:

```bash
php artisan test --testsuite=Unit --filter=Pricing
```

## Deployment

Ships as a Docker image (FrankenPHP) to a single Render **Web Service**, with **Neon**
as the database. Domain SQL is applied by `php artisan db:apply` (tracked, safe to run
on every deploy). Full steps, environment variables, and the pre-deploy command are in
[`docs/deployment.md`](docs/deployment.md).

## Project structure

```
app/Domain/Pricing/     the pure pricing engine (weights, colours, rounding)
app/Livewire/           the tool, result page, dashboard (Livewire components)
db/migrations/          hand-authored SQL — the source of truth for the schema
docs/                   decisions log, spec, deployment notes
design/                 design prompts and reference mockups
tests/Unit/Pricing/     engine test suite
```

## Built on Laravel

This project is a [Laravel](https://laravel.com) application. Laravel provides the
routing, Eloquent models, Blade templating, and the Livewire/Vite tooling that the app
is built on.

## License

MIT.
