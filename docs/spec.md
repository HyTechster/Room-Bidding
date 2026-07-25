# Room Bidding — Restated Specification

This is my unambiguous restatement of the source brief (CLAUDE.md Parts 1–4),
with the pricing engine (3.5) written in my own notation. Where the brief was
ambiguous, the resolution is recorded in [decisions.md](decisions.md).

Scope for v1: cases **C-i** (tenants < capacity) and **C-ii** (tenants = capacity).
Case **C-iii** (tenants > capacity) is detected and blocked, schema-ready for later.

---

## 1. Roles

- **Host** — the only registered account (free sign-up). Sets up the house, pays
  per session, controls flow (Start / Proceed / End Bid). **Also a tenant** and
  bids identically to everyone else.
- **Member (tenant)** — no account. Joins via a single invite link, enters a
  display name, confirms, and participates.

## 2. Inputs & derived values

Host provides exactly five inputs:

1. `R` — total house rent (fixed, known).
2. `N` — number of tenants, **including the host**.
3. `M` — number of rooms.
4. `C_j` — max occupants **per room** `j` (individually set).
5. Offset — step size for price movement between rounds; host picks the unit:
   **percentage** or **fixed currency amount**.

Derived:

- **Total capacity** `Cap = Σ_j C_j`.
- Invite link is used by `N − 1` members (host takes one slot).

Setup rules:

- A: `R` given. B: `M` reflects real house. D: every `C_j` set.
- C (tenant count vs capacity):
  - C-i: `N < Cap` → in scope.
  - C-ii: `N == Cap` → in scope.
  - C-iii: `N > Cap` → **blocked** at setup with a "not yet supported" message.

## 3. Session lifecycle

1. Host enters inputs and chooses **anonymity**: *Names visible* or *Anonymous*.
2. App generates **one invite link** (expires at End Bid, and automatically 7 days
   after creation — whichever first).
3. Each member opens link → enters display name → confirms → lands in **lobby**.
4. Lobby: every member marks **Ready**. Host appears in the roster as a participant.
5. Host starts the bid when all are ready. **Roster locks at round-1 start** (no
   late joiners; count fixed, force-remove may only reduce it).

### Anonymity safeguard (host is also a bidder)

- *Names visible*: nothing hidden, ever.
- *Anonymous*:
  - Lobby: host **may** see real names (to verify the right people joined).
  - From round 1: host's view is anonymised like everyone else's; name mapping hidden.
  - At **End Bid**: all real names revealed to everyone, permanently.

## 4. A bidding round

Everyone sees, live: every room, its `C_j`, its current per-person price, live
occupancy `n_j`, and each room's status colour.

Per-participant state machine for their chosen slot:

- **Pick** — freely choose/change any room.
- **Lock** — social signal "I've decided"; can still **unlock** and change.
- **Confirm** — final for this round; cannot unlock or change.

The host may **Proceed** only when **every participant — host included — has
Confirmed** (not merely Locked). When the last confirm lands, occupancy is frozen
and snapshotted (A1); that snapshot drives colour evaluation and the weight update.

### Room status colours (per room, at round end)

Let `n` = occupants who chose room `j`, `C` = `C_j`:

- 🟢 **Green**: `n == C` (exactly full).
- 🟡 **Yellow**: `n < C` (under-filled; expected when `N < Cap`).
- 🔴 **Red**: `n > C` (over-subscribed; **blocking**).

- **Any red** ⇒ host cannot end; system **auto-advances** to the next round with
  recalculated prices (host does not press start again).
- **No red** (all green, or green+yellow mix) ⇒ host may **End Bid**.

## 5. Pricing engine (my notation of spec 3.5)

Two non-negotiable properties:

- **P1 — Budget balance:** `Σ_j n_j · p_j = R` exactly, every round and at settlement.
- **P2 — Symmetric fairness:** a +offset then −offset (or −then+) at the same
  damping returns a room to its exact prior relative position.

### 5.1 Two layers

- **Layer 1 — weights.** Each room `j` has `w_j > 0`, its per-person desirability.
  Only ratios matter. All start `w_j = 1`.
- **Layer 2 — prices (derived).** Given `R` and live occupancy `n_j`:

  ```
  p_j = R · w_j / D,   where D = Σ_k (n_k · w_k)
  ```

  Room total = `n_j · p_j`. Summing: `Σ_j n_j p_j = R · (Σ_j n_j w_j)/D = R`.
  → **P1 holds structurally** (no reconciliation step, no drift). A room with
  `n_j = 0` drops out of `D` (distorts nobody) yet still advertises `p_j = R·w_j/D`.

  **Round-1 check:** all `w_j = 1` ⇒ `p_j = R/Σn_k = R/N` (equal split).

### 5.2 Offset normalisation → single rate δ

- Percentage `P` ⇒ `δ = P / 100`.
- Fixed amount `A` ⇒ `δ = A / (R / N)` (amount as fraction of round-1 baseline).

Both units then drive identical multiplicative machinery.

### 5.3 Weight updates (multiplicative, between rounds only)

```
increase (RED):    w_j ← w_j · (1 + rate)
decrease (YELLOW): w_j ← w_j / (1 + rate)
static  (GREEN):   w_j ← w_j
```

`×(1+rate)` and `÷(1+rate)` are exact inverses ⇒ **P2 holds**. Also keeps
`w_j > 0` forever ⇒ **prices never reach 0 or go negative** (no floor needed).

### 5.4 Unified transition rule

Each room keeps a flip counter `f` (starts 0) = consecutive yellow↔red flips since
it last touched green. **Round 1: treat previous colour as GREEN for every room.**

```
if current == GREEN:
    f = 0
    static (no weight change)
else:
    if (prev == YELLOW and cur == RED) or (prev == RED and cur == YELLOW):
        f = f + 1
    rate = δ / 2^f
    if cur == RED:     w_j ← w_j · (1 + rate)
    if cur == YELLOW:  w_j ← w_j / (1 + rate)
```

Reproduces the full transition table:

| Prev → Cur | `f` | rate | Effect |
|---|---|---|---|
| 🟢→🟢 | reset 0 | — | static |
| 🟢→🟡 | 0 | δ | cheaper (full) |
| 🟢→🔴 | 0 | δ | dearer (full) |
| 🟡→🟢 | reset 0 | — | static |
| 🟡→🟡 | unchanged | δ/2^f | cheaper (damped) |
| 🟡→🔴 | +1 | δ/2 (first flip) | dearer (half) |
| 🔴→🟢 | reset 0 | — | static |
| 🔴→🟡 | +1 | δ/2 or δ/4 … | cheaper (damped) |
| 🔴→🔴 | unchanged | δ/2^f | dearer (damped) |

Damping is **per-room** — one room's `f` never affects another room's rate in the
same round. Green is the only reset. Same-colour transitions keep earned damping
(refinement recorded in decisions.md).

Worked damping (green→red→yellow→red→yellow):
`f = 0 (rate δ) → 1 (δ/2) → 2 (δ/4) → 3 (δ/8)`; a green resets to `f = 0`.

### 5.5 Price display during a round

Prices are always derived live from `p_j = R·w_j/D` with **current live occupancy**.
- Displayed price is always the true price; totals always sum to R.
- Prices shift as people move mid-round — this is the core signal; UI must show it
  ("if the round ended now, you pay X").
- Weights change only between rounds, so start-of-round prices are continuous with
  the previous round's end when nobody has moved.
- At End Bid the same formula with final occupancy settles exactly R.

### 5.6 Rounding (largest-remainder)

Work in integer minor units (sen), exact/high-precision arithmetic. At settlement:
round every per-person amount down, then distribute leftover sen one at a time to
the largest fractional remainders, ties broken by stable order (room index, then
join order), so rounded amounts sum to **exactly R**.

### 5.7 Fixture (test suite anchor)

`R = 3000`, caps `[2,2,2,1]`, `Cap = 7`, `N = 6` (C-i), offset 10% ⇒ `δ = 0.10`.

- **Round 1:** `w = [1,1,1,1]`; every room 500/person. Final occupancy `[3,2,1,0]`
  ⇒ colours `[RED, GREEN, YELLOW, YELLOW]`. Check `3·500+2·500+1·500 = 3000` ✓.
- **Weight update** (prev = GREEN, `f = 0`): `w = [1.10, 1.0, 0.90909…, 0.90909…]`.
- **Round 2 opening** (occupancy `[3,2,1,0]`): `D = 3·1.1 + 2·1 + 1·0.90909 = 6.20909`.
  Prices: `531.47 / 483.16 / 439.24 / (439.24 advertised)`.
  Check `3·531.47 + 2·483.16 + 1·439.24 = 3000.00` ✓.

## 6. Ending

1. Once no room is red, host's only action is **End Bid**.
2. Displays every room, final per-person price, each person's exact amount, room
   totals — all summing exactly to R.
3. **All real names revealed** (anonymity ends permanently).
4. Invite link **expires immediately** (and in any case 7 days after creation).
5. Final result **permanently viewable** by host + members (permanent result URL),
   printable/exportable.

## 7. Accounts, sessions, payment

- Host account: free. Creating a **bidding session**: one-off payment per session.
- Paid session is **consumed on End Bid**.
- Before End: host may log out/in and **resume exactly** where left off.
- After End: result available indefinitely; session cannot be reopened/re-bid.
- (Provider/price deferred — see Q2 in decisions.md.)
