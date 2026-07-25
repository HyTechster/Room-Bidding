# Decisions Log

Every project decision is appended here with a date and a one-line rationale (R8).
Newest entries at the bottom of each section.

---

## Tech stack & infrastructure

| Date | Decision | Rationale |
|---|---|---|
| 2026-07-25 | **Framework: Laravel (full-stack) + Livewire** | Single deployable app; server-rendered reactive UI; strong auth/migrations/queue ecosystem; one language (PHP). |
| 2026-07-25 | **Realtime: Laravel Reverb** | First-party WebSocket server for live occupancy, colours, prices, and the pick/lock/confirm state machine. |
| 2026-07-25 | **Hosting: Render** | Runs PHP + a persistent Reverb worker. Free tier for dev (tolerating cold start); paid ~$7/mo web + worker for live sessions. |
| 2026-07-25 | **Database: Supabase (Postgres) — database only** | Managed Postgres via connection string. Laravel handles auth + realtime itself; we do NOT use Supabase Auth/Realtime/RLS. |
| 2026-07-25 | **Database host changed: Supabase → Neon (Postgres)** | Supabase free project hit its limit. Neon is managed serverless Postgres with a generous free tier and fast wake. Schema/code unchanged (plain Postgres); only `.env` differs. Region ap-southeast-1; PostgreSQL 18.4. |
| 2026-07-25 | **Auth: Laravel Breeze (Livewire stack)** | Standard, minimal register/login/logout/password-reset as Livewire (Volt) components with database-driven session persistence; Tailwind + Alpine + Vite as the CSS foundation (restyled later against `design/`). |
| 2026-07-25 | **HTTP session persistence: database driver** (`sessions` table on Neon) | Survives Render instance restarts so the host can log out/in and resume (Part 3.7). |
| 2026-07-25 | **Cold start / pause accepted for dev** | Render sleeps after ~15 min but auto-wakes (~30–60s) — only hits the first request; live sessions stay warm from ongoing traffic. Supabase pauses after ~7 days idle and needs a manual restore — avoided by weekly use. Go paid tiers for production. |

## Data & SQL

| Date | Decision | Rationale |
|---|---|---|
| 2026-07-25 | **SQL-first migrations, canonical in `db/migrations/`** (R5) | Hand-authored raw Postgres SQL is the source of truth, applied to Supabase directly. Eloquent is used as a query/model layer only — never as the schema source. Numbered, forward-only files. |
| 2026-07-25 | **R5 scope: framework tables Laravel-managed, domain tables authored in `db/`** | `users`, HTTP `sessions`, `cache`, `jobs` etc. stay in Laravel's bundled migrations (framework plumbing). The product's entire domain schema is hand-authored in `db/migrations/001_init.sql` and references `users(id)`. Apply order: `artisan migrate` (framework) then `db/*.sql` (domain). **Flagged for host confirmation.** |
| 2026-07-25 | **Domain "session" table named `bidding_sessions`** | Avoids colliding with Laravel's HTTP `sessions` table (database session driver). |
| 2026-07-25 | **Money in BIGINT sen; weights in exact `NUMERIC`; enums as `TEXT` + `CHECK`** | Integer minor units per 3.5.6; arbitrary-precision NUMERIC keeps weights exact (never float); CHECK-constrained text columns are simpler to evolve than Postgres ENUM types. |
| 2026-07-25 | **Engine uses exact rational arithmetic (`Rational`, BCMath big-int fractions)** | Guarantees P1 (budget balance) and P2 (symmetry) *exactly*, not approximately. No binary floats, no fixed-scale decimals anywhere in money/weights. Pure `App\Domain\Pricing`, zero framework deps (R10). |
| 2026-07-25 | **Worked-example (3.5.7) display figures are hand-rounded in the spec** | Exact round-2 prices are 36300000/683, 33000000/683, 30000000/683 sen. The spec's printed 531.47/483.16/439.24 are ~2dp hand-rounds; half-up display gives 531.48/483.16/439.24. Tests assert the exact rationals + exact budget balance; settlement (largest-remainder) sums to exactly R. |
| 2026-07-25 | **`room_round_states` stores weight/flip as `*_start` and `*_end`** | Full round replay/audit: `_start` = values used to price the round, `_end` = values after the end-of-round transition update carried into the next round. |
| 2026-07-25 | **Explicit `settlements` + `settlement_lines` tables** | Guarantees a permanent, printable final result (Part 3.6) with per-person amounts summing to exactly R, independent of live state. |
| 2026-07-25 | **Setup wizard = one Livewire class component, 3 steps** | Basics → rooms/capacity → review. Host auto-enrolled as participant #1 (`is_host`, `join_order=1`). Money entered in MYR, stored as sen. Scope (C-i/C-ii/C-iii) derived live; C-iii blocks creation. |
| 2026-07-25 | **Test-DB strategy DEFERRED** | `phpunit.xml` forces in-memory SQLite, but the domain schema is Postgres-specific raw SQL, so DB-touching feature tests can't run there yet. Phase 5 engine tests are pure PHP (unaffected). A Postgres test strategy (dedicated test DB or a SQLite-compatible schema) will be set up before feature tests that need the DB. Phase 4 was verified via a Livewire interaction smoke test against Neon with cleanup. |
| 2026-07-25 | **Members identified by a per-browser cookie holding their `participant_token`** | No account for members (Part 3.1). The cookie (`rb_participant_{sessionId}`, 7 days) lets a member reconnect/resume and prevents double-join from the same browser. |
| 2026-07-25 | **Invite link auto-created when host opens the manage page; status draft→lobby** | One invite link per session (`max_uses = N−1`), expiring with the session (7 days / End Bid). Roster stays open only while status is draft/lobby (no late joiners once bidding starts — Q5). |
| 2026-07-25 | **Lobby uses polling (`wire:poll.3s`) for now** | Near-real-time roster/ready updates without Reverb. Upgraded to Reverb push in Phase 7. |
| 2026-07-25 | **Realtime via Reverb + a content-free `SessionPing` on a PUBLIC channel `session.{id}`** | Members have no account, so private/presence auth is awkward. The ping carries no participant data; clients react with Livewire `$refresh`, which re-renders applying anonymity server-side — no names/mapping ever go on the wire. |
| 2026-07-25 | **`SessionPing` is `ShouldBroadcastNow`; dispatches wrapped in `rescue(...)`** | Synchronous broadcast needs no queue worker. `rescue` makes a broadcast failure non-fatal, so if Reverb is down the user action still succeeds — polling (now `wire:poll.10s`) is the fallback. |
| 2026-07-25 | **Polling kept as a 10s fallback alongside Echo** | Belt-and-braces: instant via Reverb when running, still-eventually-consistent via poll when it isn't (e.g. local dev without `reverb:start`). |
| 2026-07-25 | **Exact weights persisted as `weight_*_frac` TEXT ("num/den") — migration `002`** | The NUMERIC weight columns are lossy; the engine (P2) needs exact rationals across rounds. The frac columns are the source of truth the engine reads/writes; NUMERIC is display-only. |
| 2026-07-25 | **Auto-advance on all-confirmed + any red; no red enables End Bid (host's "Proceed" = End)** | Matches 3.4 "system automatically advances… host does not press start again." Triggered server-side when the last confirm lands. With no red, the host's only remaining action is End Bid (spec 3.6). |
| 2026-07-25 | **Round cap → `cap_reached` status → host Force End** | At `round_cap` (Q3) with a room still red, the session freezes and the host force-ends, settling at current occupancy (simplified tie-break; over-capacity is physical, not a math error — settlement still sums to R). |
| 2026-07-25 | **Selections carry over into the next round (room preserved, state reset to `pick`)** | Gives price continuity at round start (3.5.5): opening prices = new weights at the carried occupancy; participants must re-confirm each round. |
| 2026-07-25 | **End Bid: snapshot final round → largest-remainder settlement → revoke invite → mint `result_token` → status `ended`** | Per-tenant amounts (weighted by room) sum to exactly R; names revealed; invite expires immediately (3.6); permanent result token minted for Q7 (result page built in Phase 9). |
| 2026-07-25 | **Permanent result page at public `/result/{token}` (Q7)** | Read-only, no account, independent of the invite link, survives its expiry. Shows per-person + per-room breakdown, the visible "sums to exactly R" proof, revealed real names, and a round-by-round audit read from persisted `room_round_states`. Print/PDF via `window.print()` + a `@media print` stylesheet. |
| 2026-07-25 | **Lifecycle cleanup: `sessions:expire` command, scheduled daily** | Marks unfinished sessions past `expires_at` (7 days) as `expired` and revokes their invite links; ended sessions are never touched (results kept permanently, A7). Idempotent. Manage/join pages also detect expiry defensively so state is correct even before the job runs. |
| 2026-07-25 | **Resume works from persisted state; dashboard split Active / Completed** | All session state lives in the DB, so the host reopening the manage page continues exactly where they left off (verified mid-bidding), and members resume via their cookie. Dashboard lists active sessions (Resume) separately from ended/expired (View / Result). |
| 2026-07-25 | **Hardening: rate limiting, accessibility, mobile, deployment doc** | Join action rate-limited per IP (20/min) + public join/result routes throttled (60/min). Colour is never the only signal — green/yellow/red always paired with icon + text; room cards expose `aria-pressed`/`aria-label`. Member bidding view widened on desktop. `docs/deployment.md` covers Render web + Reverb worker + Neon, schema apply order, `schedule:run` cron, and env. |

## Pricing engine

| Date | Decision | Rationale |
|---|---|---|
| 2026-07-25 | **Two-layer model (weights → derived prices)** exactly per spec 3.5 | Budget balance (P1) and symmetry (P2) hold structurally. Implemented as a pure, framework-independent PHP module with tests before any UI (R10). |
| 2026-07-25 | **Refinement over source table: same-colour transitions (red→red, yellow→yellow) keep earned damping `δ/2^f` instead of resetting to full offset** | Resetting to full offset mid-oscillation would undo damping and prevent convergence. Green is the ONLY thing that resets `f`. (Mandated by spec 3.5.4.) |
| 2026-07-25 | **Money in integer minor units (sen); exact/high-precision arithmetic, never binary floats** | Correctness of settlement; largest-remainder rounding sums to exactly R. |

## Open questions — resolutions (Part 4 + extra ambiguities)

Baseline = my recommended answers, accepted to keep momentum. Any of these can be
changed on request; changes will be appended here, not edited in place.

| Ref | Question | Resolution (2026-07-25) |
|---|---|---|
| Q1 | Member disconnect / abandonment | Live presence + idle indicator; host **force-remove between rounds only**, which decrements tenant count and re-checks C-i/C-ii scope. No hard per-round timer in v1 (soft "waiting for X" nudge). |
| Q2 | Payment provider / price | **DEFERRED (provider).** Phase 10 shipped a sandbox gate: session locked until paid (Start gated), consumed on End Bid. Price config `billing.session_price_cents` (default RM 10.00), currency MYR. Real provider (ToyyibPay / Billplz / Stripe) drops in behind `PaymentService::pay()` + a webhook with no UI/orchestration changes. |
| Q3 | Max round count | Safety cap **20 rounds**, configurable. On reaching cap, host may **force-end**; any still-red room resolved by deterministic tie-break (earliest confirm/join order fills capacity, overflow settled by tie-break). |
| Q4 | Empty-room display floor | No mathematical floor (prices are always > 0). **Presentational** cap on the displayed drop + clear "Empty — most affordable" label. |
| Q5 | Late joiners | **No.** Roster locks when round 1 starts; tenant count fixed for the session (force-remove may only reduce it). |
| Q6 | Change offset mid-session | **Allowed between rounds only**, host-only, **logged visibly to all** ("Host changed offset 10% → 15% before round 4"). Never retroactive; P1/P2 preserved. |
| Q7 | Result sharing after link expiry | **Yes.** Separate **permanent read-only result URL** with its own unguessable token, minted at End Bid, independent of the invite link. |
| A1 | Round-boundary occupancy freeze | When the last participant Confirms, occupancy is snapshotted; that snapshot drives colour evaluation + weight update. No moves after the final confirm. |
| A2 | Green room while others red | Any red ⇒ advance regardless of greens. Green rooms are static (weights unchanged); their displayed price may still move because OTHER rooms' weights changed. UI explains this. |
| A3 | Two people confirm same over-capacity room | Allowed — that is how a room goes red. Confirm is per-person finality, **not** a capacity gate. |
| A4 | Currency / minor unit | **MYR**, minor unit = sen (1/100). Ties into Q2. |
| A5 | Host auth method | **Email + password** for v1 (simplest, stack-native). |
| A6 | Force-remove effect on weights/`f` | Weights and `f` persist; only `n_j` changes and rooms recolour on next evaluation. Removal never resets `f`. |
| A7 | Retention | Results retained indefinitely (permanent result URL). Live-session state + invite links purged on End Bid and at 7 days. |
