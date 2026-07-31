<?php

namespace App\Livewire;

use App\Domain\Pricing\Colour;
use App\Domain\Pricing\Offset;
use App\Domain\Pricing\PricingEngine;
use App\Domain\Pricing\Rational;
use App\Domain\Pricing\Settlement;
use App\Models\BiddingSession;
use App\Models\Participant;
use App\Models\Room;
use App\Models\Round;
use App\Models\RoomRoundState;
use App\Models\Settlement as SettlementModel;
use App\Models\SettlementLine;
use App\Support\Currency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Single-operator room-bidding tool. Holds the whole session in-memory (public
 * props) and computes everything through the pure pricing engine, so guests can
 * use it with nothing saved. Saving to the database is layered on top.
 *
 * Prices are frozen while people are being placed and only recalculated when the
 * host presses Continue. Weights are carried across rounds as exact fraction
 * strings ("num/den") so they survive Livewire's JSON round trips.
 */
#[Layout('layouts.public')]
class RoomBiddingTool extends Component
{
    public string $step = 'setup';           // setup | bidding | result

    // ---- setup ----
    public string $rent = '';
    public string $currency = 'MYR';
    public string $offset_unit = 'percentage';
    public string $offset_value = '10';
    /** @var array<int, array{label:?string, capacity:int}> */
    public array $rooms = [];
    public string $namesText = '';

    // ---- bidding state ----
    public array $names = [];
    public int $roundNo = 1;
    public array $weights = [];               // per room index: "num/den"
    public array $flips = [];                 // per room index: int
    public array $prevColours = [];           // per room index: 'green'|'yellow'|'red'
    public array $assignment = [];            // participant index => room index | null
    public array $displayedPrices = [];       // per room index: frozen price in cents
    public array $previousPrices = [];        // per room index: price shown before the last Next
    public array $displayedColours = [];      // per room index: committed colour from the last Next
    public bool $readyToSettle = false;       // last Next found no red room, so Finish is available
    public ?int $selectedPi = null;           // tap-to-place selection

    // ---- result ----
    public array $result = [];
    public int $resultTotal = 0;
    public array $history = [];
    public ?string $savedToken = null;

    public function mount(): void
    {
        if (empty($this->rooms)) {
            $this->rooms = [
                ['label' => null, 'capacity' => 2],
                ['label' => null, 'capacity' => 2],
            ];
        }
    }

    // ---- helpers ----

    public function money(int $cents): string
    {
        return Currency::format($cents, $this->currency);
    }

    public function currencyOptions(): array
    {
        return Currency::options();
    }

    private function rentCents(): int
    {
        return (int) round(((float) $this->rent) * 100);
    }

    // ---- setup actions ----

    public function addRoom(): void
    {
        $this->rooms[] = ['label' => null, 'capacity' => 1];
    }

    public function removeRoom(int $i): void
    {
        unset($this->rooms[$i]);
        $this->rooms = array_values($this->rooms);
    }

    public function getParsedNamesProperty(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $this->namesText))
            ->map(fn ($n) => trim($n))
            ->filter(fn ($n) => $n !== '')
            ->values()
            ->all();
    }

    public function getTotalCapacityProperty(): int
    {
        return array_sum(array_map(fn ($r) => max(0, (int) ($r['capacity'] ?? 0)), $this->rooms));
    }

    public function getScopeProperty(): ?string
    {
        $n = count($this->parsedNames);
        $cap = $this->totalCapacity;
        if ($n === 0 || $cap <= 0) {
            return null;
        }
        return match (true) {
            $n < $cap  => 'c_i',
            $n === $cap => 'c_ii',
            default    => 'c_iii',
        };
    }

    public function startBidding(): void
    {
        $this->resetErrorBag();

        // Coerce dropdown/segmented values to known-safe options (defensive).
        if (! array_key_exists($this->currency, Currency::MAP)) {
            $this->currency = 'MYR';
        }
        if (! in_array($this->offset_unit, ['percentage', 'fixed'], true)) {
            $this->offset_unit = 'percentage';
        }

        $names = $this->parsedNames;

        if (! is_numeric($this->rent) || (float) $this->rent <= 0 || (float) $this->rent > 100000000) {
            $this->addError('rent', 'Enter a total rent greater than 0.');
        }
        if (! is_numeric($this->offset_value) || (float) $this->offset_value <= 0
            || ($this->offset_unit === 'percentage' && (float) $this->offset_value > 100)) {
            $this->addError('offset_value', $this->offset_unit === 'percentage'
                ? 'Enter a percentage between 0 and 100.'
                : 'Enter an offset amount greater than 0.');
        }
        if (count($this->rooms) < 1) {
            $this->addError('rooms', 'Add at least one room.');
        }
        foreach ($this->rooms as $i => $r) {
            $cap = $r['capacity'] ?? '';
            if (! is_numeric($cap) || (float) $cap != floor((float) $cap) || (int) $cap < 1 || (int) $cap > 1000) {
                $this->addError("rooms.$i.capacity", 'Capacity must be a whole number of at least 1.');
            }
        }
        if (count($names) < 1) {
            $this->addError('namesText', 'Enter at least one participant.');
        } elseif (count($names) > 500) {
            $this->addError('namesText', 'That is a lot of people. Please keep it under 500.');
        } elseif ($this->scope === 'c_iii') {
            $this->addError('namesText', 'You have more people than total capacity. Add room capacity or remove people.');
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        // Normalise capacities to plain integers now that they are valid.
        foreach ($this->rooms as $i => $r) {
            $this->rooms[$i]['capacity'] = (int) $r['capacity'];
        }

        $roomCount = count($this->rooms);
        $baseline = intdiv($this->rentCents(), max(1, count($names)));

        $this->names = $names;
        $this->roundNo = 1;
        $this->weights = array_fill(0, $roomCount, '1/1');
        $this->flips = array_fill(0, $roomCount, 0);
        $this->prevColours = array_fill(0, $roomCount, Colour::Green->value);
        $this->assignment = array_fill(0, count($names), null);
        $this->displayedPrices = array_fill(0, $roomCount, $baseline);
        $this->previousPrices = [];
        $this->displayedColours = array_fill(0, $roomCount, null);
        $this->readyToSettle = false;
        $this->selectedPi = null;
        $this->step = 'bidding';
    }

    // ---- bidding actions ----

    /** Tap a name to select it (tap again to deselect). Works alongside drag. */
    public function selectChip(int $pi): void
    {
        $this->selectedPi = ($this->selectedPi === $pi) ? null : $pi;
    }

    /** Tap a room: place the selected name there. */
    public function tapRoom(int $roomIndex): void
    {
        if ($this->selectedPi !== null) {
            $this->assign($this->selectedPi, $roomIndex);
        }
    }

    /** Tap the unassigned area: return the selected name to it. */
    public function tapPool(): void
    {
        if ($this->selectedPi !== null) {
            $this->assign($this->selectedPi, null);
        }
    }

    /** Drag-drop or tap assignment. $roomIndex null returns the person to the pool. */
    public function assign($participantIndex, $roomIndex = null): void
    {
        $pi = (int) $participantIndex;
        if (array_key_exists($pi, $this->assignment)) {
            $this->assignment[$pi] = ($roomIndex === null || $roomIndex === '') ? null : (int) $roomIndex;
        }
        $this->selectedPi = null;
        // Any move changes the placement, so the shown prices need a fresh Next.
        $this->readyToSettle = false;
    }

    private function delta(): Rational
    {
        if ($this->offset_unit === 'percentage') {
            return Offset::fromPercentage(Rational::fromDecimalString((string) $this->offset_value));
        }
        $amountCents = (int) round(((float) $this->offset_value) * 100);
        return Offset::fromFixedAmount($amountCents, $this->rentCents(), max(1, count($this->names)));
    }

    /** @return int[] occupancy per room index */
    private function occupancy(): array
    {
        $occ = array_fill(0, count($this->rooms), 0);
        foreach ($this->assignment as $roomIndex) {
            if ($roomIndex !== null && isset($occ[$roomIndex])) {
                $occ[$roomIndex]++;
            }
        }
        return $occ;
    }

    /** @return Rational[] weights per room index */
    private function weightRationals(): array
    {
        return array_map(fn ($w) => Rational::fromFractionString($w), $this->weights);
    }

    /** Live occupancy and colour per room; price is the frozen displayed price. */
    public function roomData(): array
    {
        $occ = $this->occupancy();
        $out = [];
        foreach ($this->rooms as $i => $room) {
            $w = Rational::fromFractionString($this->weights[$i] ?? '1/1');
            $cmp = $w->compareTo(Rational::fromInt(1));
            $out[$i] = [
                'occupancy'       => $occ[$i],
                'colour'          => Colour::determine($occ[$i], (int) $room['capacity']),
                'price_cents'     => (int) ($this->displayedPrices[$i] ?? 0),
                'previous_cents'  => array_key_exists($i, $this->previousPrices) ? (int) $this->previousPrices[$i] : null,
                'committedColour' => $this->displayedColours[$i] ?? null,
                'weightState'     => $cmp > 0 ? 'up' : ($cmp < 0 ? 'down' : 'same'),
            ];
        }
        return $out;
    }

    /**
     * "Next": recalculate the prices for the current placement. If a room is over
     * capacity, weights evolve and the round advances. If no room is over capacity,
     * the prices are refreshed and Finish becomes available. Nothing settles here,
     * so the board always shows the prices that a Finish would charge.
     */
    public function continueRound(): void
    {
        $this->resetErrorBag();

        if (in_array(null, $this->assignment, true)) {
            $this->addError('board', 'Place every participant in a room first.');
            return;
        }

        // Whatever is currently shown becomes the "previous" price after this Next.
        $this->previousPrices = $this->displayedPrices;

        $occ = $this->occupancy();
        $currColours = [];
        foreach ($this->rooms as $i => $room) {
            $currColours[$i] = Colour::determine($occ[$i], (int) $room['capacity']);
        }
        $anyRed = collect($currColours)->contains(fn ($c) => $c === Colour::Red);
        $this->selectedPi = null;

        if ($anyRed) {
            // A round is committed: record it, evolve weights, then show new prices.
            $pricesNow = PricingEngine::derivePrices($this->rentCents(), $this->weightRationals(), $occ);
            $this->recordHistory($occ, $currColours, $pricesNow);

            $prev = array_map(fn ($c) => Colour::from($c), $this->prevColours);
            $update = PricingEngine::updateWeights($prev, $currColours, $this->weightRationals(), $this->flips, $this->delta());

            $this->weights = array_map(fn (Rational $w) => $w->toFractionString(), $update['weights']);
            $this->flips = $update['flips'];
            $this->prevColours = array_map(fn (Colour $c) => $c->value, $currColours);
            $this->roundNo++;
            $this->readyToSettle = false;

            $newPrices = PricingEngine::derivePrices($this->rentCents(), $update['weights'], $occ);
            $this->displayedPrices = array_map(fn (Rational $p) => (int) $p->roundHalfUpInt(), $newPrices);
            $this->displayedColours = array_map(fn (Colour $c) => $c->value, $currColours);

            session()->flash('round_note', "Round {$this->roundNo}. Prices updated. Move people out of the over capacity rooms, then press Next again.");
            return;
        }

        // No room over capacity: refresh the shown prices and enable Finish.
        $pricesNow = PricingEngine::derivePrices($this->rentCents(), $this->weightRationals(), $occ);
        $this->displayedPrices = array_map(fn (Rational $p) => (int) $p->roundHalfUpInt(), $pricesNow);
        $this->displayedColours = array_map(fn (Colour $c) => $c->value, $currColours);
        $this->readyToSettle = true;

        session()->flash('round_note', 'Prices updated for this placement. Press Finish to see who pays, or keep rearranging.');
    }

    /** Settle using exactly the prices currently shown on the board. */
    public function finish(): void
    {
        $this->resetErrorBag();

        if (in_array(null, $this->assignment, true)) {
            $this->addError('board', 'Place every participant in a room first.');
            return;
        }

        $occ = $this->occupancy();
        $currColours = [];
        foreach ($this->rooms as $i => $room) {
            $currColours[$i] = Colour::determine($occ[$i], (int) $room['capacity']);
        }
        if (collect($currColours)->contains(fn ($c) => $c === Colour::Red)) {
            $this->addError('board', 'A room is over capacity. Move people out and press Next before finishing.');
            return;
        }

        $pricesNow = PricingEngine::derivePrices($this->rentCents(), $this->weightRationals(), $occ);
        $this->recordHistory($occ, $currColours, $pricesNow);
        $this->settle($occ, $pricesNow);
    }

    private function recordHistory(array $occ, array $currColours, array $prices): void
    {
        $rooms = [];
        foreach ($this->rooms as $i => $room) {
            $rooms[$i] = [
                'occupancy'   => $occ[$i],
                'colour'      => $currColours[$i]->value,
                'price_cents' => (int) $prices[$i]->roundHalfUpInt(),
                'weight_frac' => $this->weights[$i],
            ];
        }
        $this->history[] = ['round_no' => $this->roundNo, 'rooms' => $rooms];
    }

    private function settle(array $occ, array $prices): void
    {
        $rows = [];
        foreach ($this->assignment as $pi => $roomIndex) {
            $rows[] = ['pi' => $pi, 'room' => (int) $roomIndex];
        }
        usort($rows, fn ($a, $b) => [$a['room'], $a['pi']] <=> [$b['room'], $b['pi']]);

        $amounts = array_map(fn ($r) => $prices[$r['room']], $rows);
        $rounded = Settlement::largestRemainder($amounts, $this->rentCents());

        $this->result = [];
        foreach ($rows as $k => $r) {
            $this->result[] = [
                'name'         => $this->names[$r['pi']],
                'room'         => $this->rooms[$r['room']]['label'] ?: 'Room '.($r['room'] + 1),
                'amount_cents' => $rounded[$k],
                'pi'           => $r['pi'],
                'room_index'   => $r['room'],
            ];
        }
        $this->resultTotal = array_sum($rounded);
        $this->step = 'result';
    }

    public function backToSetup(): void
    {
        $this->step = 'setup';
    }

    public function restart(): void
    {
        $this->reset(['names', 'roundNo', 'weights', 'flips', 'prevColours', 'assignment', 'displayedPrices', 'previousPrices', 'displayedColours', 'readyToSettle', 'selectedPi', 'result', 'resultTotal', 'history', 'savedToken']);
        $this->step = 'setup';
    }

    /**
     * Persist the finished result (logged-in users only) so it appears in
     * "My results" and on the permanent result page.
     */
    public function saveResult(): void
    {
        if (! Auth::check() || $this->step !== 'result' || $this->savedToken) {
            return;
        }

        $rentCents = $this->rentCents();
        $names = $this->names;
        $token = Str::random(48);
        $deltaFloat = $this->delta()->toFloat();

        DB::transaction(function () use ($rentCents, $names, $token, $deltaFloat) {
            $session = BiddingSession::create([
                'host_user_id'       => Auth::id(),
                'total_rent_cents'   => $rentCents,
                'num_tenants'        => count($names),
                'num_rooms'          => count($this->rooms),
                'offset_unit'        => $this->offset_unit,
                'offset_percent'     => $this->offset_unit === 'percentage' ? (float) $this->offset_value : null,
                'offset_fixed_cents' => $this->offset_unit === 'fixed' ? (int) round(((float) $this->offset_value) * 100) : null,
                'currency'           => $this->currency,
                'total_capacity'     => $this->totalCapacity,
                'scope'              => $this->scope ?? 'c_i',
                'status'             => 'ended',
                'current_round_no'   => (int) (end($this->history)['round_no'] ?? 1),
                'round_cap'          => 20,
                'result_token'       => $token,
                'expires_at'         => now()->addDays(7),
                'started_at'         => now(),
                'ended_at'           => now(),
            ]);

            $roomIds = [];
            foreach ($this->rooms as $i => $room) {
                $roomIds[$i] = Room::create([
                    'bidding_session_id' => $session->id,
                    'position'           => $i,
                    'label'              => $room['label'] ?: null,
                    'max_occupants'      => (int) $room['capacity'],
                ])->id;
            }

            $participantIds = [];
            foreach ($names as $i => $name) {
                $participantIds[$i] = Participant::create([
                    'bidding_session_id' => $session->id,
                    'user_id'            => null,
                    'is_host'            => false,
                    'display_name'       => $name,
                    'join_order'         => $i + 1,
                    'participant_token'  => Str::random(40),
                    'status'             => 'joined',
                    'is_ready'           => true,
                ])->id;
            }

            $finalRoundId = null;
            foreach ($this->history as $h) {
                $round = Round::create([
                    'bidding_session_id' => $session->id,
                    'round_no'           => $h['round_no'],
                    'status'             => 'completed',
                    'offset_unit'        => $this->offset_unit,
                    'offset_percent'     => $this->offset_unit === 'percentage' ? (float) $this->offset_value : null,
                    'offset_fixed_cents' => $this->offset_unit === 'fixed' ? (int) round(((float) $this->offset_value) * 100) : null,
                    'delta_rate'         => $deltaFloat,
                    'started_at'         => now(),
                    'completed_at'       => now(),
                ]);
                $finalRoundId = $round->id;

                foreach ($h['rooms'] as $ri => $rd) {
                    RoomRoundState::create([
                        'round_id'               => $round->id,
                        'room_id'                => $roomIds[$ri],
                        'weight_start'           => Rational::fromFractionString($rd['weight_frac'])->toFloat(),
                        'weight_start_frac'      => $rd['weight_frac'],
                        'flip_start'             => 0,
                        'occupancy'              => $rd['occupancy'],
                        'colour'                 => $rd['colour'],
                        'per_person_price_cents' => $rd['price_cents'],
                    ]);
                }
            }

            $settlement = SettlementModel::create([
                'bidding_session_id' => $session->id,
                'final_round_id'     => $finalRoundId,
                'total_rent_cents'   => $rentCents,
            ]);
            foreach ($this->result as $row) {
                SettlementLine::create([
                    'settlement_id'  => $settlement->id,
                    'participant_id' => $participantIds[$row['pi']],
                    'room_id'        => $roomIds[$row['room_index']],
                    'amount_cents'   => $row['amount_cents'],
                ]);
            }

            $this->savedToken = $token;
        });
    }

    public function render()
    {
        $unassigned = [];
        $byRoom = array_fill(0, count($this->rooms), []);
        $roomData = [];

        if ($this->step === 'bidding') {
            foreach ($this->assignment as $pi => $roomIndex) {
                if ($roomIndex === null) {
                    $unassigned[] = $pi;
                } elseif (isset($byRoom[$roomIndex])) {
                    $byRoom[$roomIndex][] = $pi;
                }
            }
            $roomData = $this->roomData();
        }

        $equalSplit = ($this->step === 'bidding' && count($this->names) > 0)
            ? intdiv($this->rentCents(), count($this->names))
            : 0;

        return view('livewire.room-bidding-tool', [
            'roomData'   => $roomData,
            'unassigned' => $unassigned,
            'byRoom'     => $byRoom,
            'allPlaced'  => $this->step === 'bidding' && ! in_array(null, $this->assignment, true),
            'anyRedNow'  => collect($roomData)->contains(fn ($d) => $d['colour'] === Colour::Red),
            'equalSplit' => $equalSplit,
        ]);
    }
}
