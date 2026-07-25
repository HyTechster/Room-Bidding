# Proposed Folder Structure

Laravel full-stack (Livewire + Reverb) with the mandated top-level `db/`,
`design/`, and `docs/` folders. Only the project-specific / rule-driven parts are
shown; standard Laravel folders (`bootstrap/`, `config/`, `public/`, `storage/`,
`vendor/`, …) exist as usual.

```
Room Bidding/
├── app/
│   ├── Domain/
│   │   └── Pricing/                # PURE engine — no Laravel deps (R10)
│   │       ├── PricingEngine.php        # weights, price derivation, transition rule
│   │       ├── Offset.php               # percentage / fixed → δ normalisation
│   │       ├── Colour.php               # GREEN / YELLOW / RED enum + n-vs-C rule
│   │       ├── RoomState.php            # value object: w, f, prev colour, n
│   │       ├── RoundResult.php          # per-room colour, price, weight after update
│   │       └── Rounding.php             # largest-remainder settlement
│   ├── Models/                     # Eloquent (query layer only, NOT schema source)
│   │   ├── User.php  Session.php  Room.php  Participant.php
│   │   ├── Round.php  RoomRoundState.php  Selection.php
│   │   ├── InviteLink.php  Payment.php
│   ├── Livewire/                   # UI components (Phase 6+)
│   │   ├── Host/                        # setup wizard, dashboard, host control strip
│   │   ├── Lobby/                       # ready state
│   │   ├── BiddingRoom/                 # room grid, pick/lock/confirm
│   │   └── Results/
│   ├── Events/                     # Reverb broadcast events (occupancy, prices, state)
│   └── Http/                       # controllers for join links, results URL, webhooks
│
├── db/                            # R5 — ALL SQL, canonical source of truth
│   ├── migrations/
│   │   └── 001_init.sql
│   ├── seeds/
│   └── README.md                       # how to apply against Supabase
│
├── design/                        # R6 — design PROMPTS as markdown, not designs
│   ├── 00_design_system.md
│   ├── 02_setup_wizard.md
│   ├── 03_lobby.md
│   ├── 04_bidding_room.md
│   └── 05_results.md
│
├── docs/
│   ├── decisions.md               # R8
│   ├── spec.md
│   ├── structure.md               # this file
│   └── deployment.md              # Render + Supabase notes (expanded in Phase 12)
│
├── resources/views/livewire/      # Blade templates for the components above
├── routes/web.php                 # host routes, /join/{token}, /result/{token}
├── tests/
│   └── Unit/Pricing/              # engine tests, incl. 3.5.7 fixture + property tests (R10)
│
├── database/                      # Laravel's own dir — kept minimal; SQL lives in db/
├── .env.example                   # tracked; real .env ignored
└── .gitignore                     # R4 (created in Phase 1)
```

Key structural rules baked in:

- **`app/Domain/Pricing/` is framework-independent** — no `Illuminate\*` imports,
  no DB, no Eloquent. It takes plain values in and returns plain values out, so it
  can be unit-tested in isolation and reused anywhere (R10).
- **Schema source of truth is `db/migrations/*.sql`** (R5). Eloquent models mirror
  it but never define it.
- **All realtime goes through `app/Events/` + Reverb**, consumed by Livewire/Echo
  in the browser — no Supabase Realtime.
