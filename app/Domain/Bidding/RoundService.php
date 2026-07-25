<?php

namespace App\Domain\Bidding;

use App\Domain\Pricing\Colour;
use App\Domain\Pricing\Offset;
use App\Domain\Pricing\PricingEngine;
use App\Domain\Pricing\Rational;
use App\Domain\Pricing\Settlement as SettlementCalc;
use App\Models\BiddingSession;
use App\Models\Round;
use App\Models\RoomRoundState;
use App\Models\Selection;
use App\Models\Settlement;
use App\Models\SettlementLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Round lifecycle + settlement, gluing the pure pricing engine to the DB.
 *
 * Exact weights are carried across rounds via the `weight_*_frac` columns
 * ("num/den"); the NUMERIC columns are decimal approximations for display only.
 */
class RoundService
{
    /** Normalised offset δ, recomputed exactly from the offset snapshot. */
    public function delta(BiddingSession $session): Rational
    {
        if ($session->offset_unit === 'percentage') {
            return Offset::fromPercentage(Rational::fromDecimalString((string) $session->offset_percent));
        }
        return Offset::fromFixedAmount((int) $session->offset_fixed_cents, (int) $session->total_rent_cents, (int) $session->num_tenants);
    }

    // -----------------------------------------------------------------------
    // Round 1
    // -----------------------------------------------------------------------

    public function startFirstRound(BiddingSession $session): Round
    {
        return DB::transaction(function () use ($session) {
            $delta = $this->delta($session);
            $opening = intdiv((int) $session->total_rent_cents, (int) $session->num_tenants); // R/N

            $round = Round::create([
                'bidding_session_id' => $session->id,
                'round_no'           => 1,
                'status'             => 'active',
                'offset_unit'        => $session->offset_unit,
                'offset_percent'     => $session->offset_percent,
                'offset_fixed_cents' => $session->offset_fixed_cents,
                'delta_rate'         => $delta->toFloat(),
                'started_at'         => now(),
            ]);

            foreach ($session->rooms as $room) {
                RoomRoundState::create([
                    'round_id'               => $round->id,
                    'room_id'                => $room->id,
                    'weight_start'           => '1',
                    'weight_start_frac'      => '1/1',
                    'flip_start'             => 0,
                    'prev_colour'            => Colour::Green->value, // round-1 convention
                    'occupancy'              => 0,
                    'per_person_price_cents' => $opening,
                ]);
            }

            $session->update([
                'status'           => 'bidding',
                'current_round_no' => 1,
                'started_at'       => now(),
            ]);

            return $round;
        });
    }

    // -----------------------------------------------------------------------
    // Live reads
    // -----------------------------------------------------------------------

    /** @return array<int,int> room_id => occupancy (anyone who has chosen the room) */
    public function liveOccupancy(Round $round): array
    {
        return Selection::where('round_id', $round->id)
            ->whereNotNull('room_id')
            ->selectRaw('room_id, count(*) as c')
            ->groupBy('room_id')
            ->pluck('c', 'room_id')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    /** @return array<int,Rational> room_id => exact weight entering the round */
    private function startWeights(Round $round): array
    {
        $out = [];
        foreach ($round->roomStates()->get() as $s) {
            $out[$s->room_id] = Rational::fromFractionString($s->weight_start_frac ?? '1/1');
        }
        return $out;
    }

    /**
     * Live per-room view: occupancy, colour, per-person price (minor units).
     *
     * @return array<int, array{occupancy:int, colour:Colour, price_cents:int}>  keyed by room_id
     */
    public function liveRoomData(BiddingSession $session, Round $round): array
    {
        $rooms = $session->rooms;
        $states = $round->roomStates()->get()->keyBy('room_id');
        $occByRoom = $this->liveOccupancy($round);

        $weights = [];
        $occupancy = [];
        foreach ($rooms as $room) {
            $weights[] = Rational::fromFractionString($states[$room->id]->weight_start_frac ?? '1/1');
            $occupancy[] = (int) ($occByRoom[$room->id] ?? 0);
        }

        $totalOcc = array_sum($occupancy);
        $out = [];

        if ($totalOcc === 0) {
            foreach ($rooms as $room) {
                $out[$room->id] = [
                    'occupancy'   => 0,
                    'colour'      => Colour::determine(0, (int) $room->max_occupants),
                    'price_cents' => (int) ($states[$room->id]->per_person_price_cents ?? 0),
                ];
            }
            return $out;
        }

        $prices = PricingEngine::derivePrices((int) $session->total_rent_cents, $weights, $occupancy);
        foreach ($rooms as $i => $room) {
            $out[$room->id] = [
                'occupancy'   => $occupancy[$i],
                'colour'      => Colour::determine($occupancy[$i], (int) $room->max_occupants),
                'price_cents' => (int) $prices[$i]->roundHalfUpInt(),
            ];
        }
        return $out;
    }

    public function allConfirmed(BiddingSession $session, Round $round): bool
    {
        $active = $session->activeParticipants()->count();
        if ($active === 0) {
            return false;
        }
        $confirmed = Selection::where('round_id', $round->id)->where('state', 'confirm')->count();
        return $confirmed === $active;
    }

    public function anyRed(BiddingSession $session, Round $round): bool
    {
        foreach ($this->liveRoomData($session, $round) as $d) {
            if ($d['colour'] === Colour::Red) {
                return true;
            }
        }
        return false;
    }

    // -----------------------------------------------------------------------
    // Advancing rounds
    // -----------------------------------------------------------------------

    /**
     * Called after a participant confirms. If everyone (host included) has
     * confirmed and any room is red, snapshot the round and advance automatically
     * (the host does not press start again). If the round cap is reached, hold in
     * 'cap_reached' for a host force-end. No red -> nothing happens (host ends).
     */
    public function maybeAdvanceOrHold(BiddingSession $session): void
    {
        $round = $session->currentRound();
        if (! $round || $round->status !== 'active' || $session->status !== 'bidding') {
            return;
        }
        if (! $this->allConfirmed($session, $round)) {
            return;
        }
        if (! $this->anyRed($session, $round)) {
            return; // no red: host may End Bid
        }

        if ($round->round_no >= $session->round_cap) {
            // Freeze the round; host must force-end (Q3).
            $this->snapshotRound($session, $round);
            $session->update(['status' => 'cap_reached']);
            return;
        }

        $this->advanceRound($session, $round);
    }

    /** Snapshot the round's final occupancy, colours, prices, and the weight update. */
    private function snapshotRound(BiddingSession $session, Round $round): array
    {
        $rooms = $session->rooms;
        $occByRoom = $this->liveOccupancy($round);
        $states = $round->roomStates()->get()->keyBy('room_id');

        $prevColours = [];
        $currColours = [];
        $weights = [];
        $flips = [];
        $occArr = [];

        foreach ($rooms as $room) {
            $state = $states[$room->id];
            $n = (int) ($occByRoom[$room->id] ?? 0);
            $colour = Colour::determine($n, (int) $room->max_occupants);

            $prevColours[] = Colour::from($state->prev_colour ?? Colour::Green->value);
            $currColours[] = $colour;
            $weights[] = Rational::fromFractionString($state->weight_start_frac ?? '1/1');
            $flips[] = (int) $state->flip_start;
            $occArr[] = $n;
        }

        $prices = array_sum($occArr) > 0
            ? PricingEngine::derivePrices((int) $session->total_rent_cents, $weights, $occArr)
            : array_map(fn ($s) => null, $rooms->all());

        $delta = $this->delta($session);
        $update = PricingEngine::updateWeights($prevColours, $currColours, $weights, $flips, $delta);

        foreach ($rooms as $i => $room) {
            $state = $states[$room->id];
            $state->occupancy = $occArr[$i];
            $state->colour = $currColours[$i]->value;
            if ($prices[$i] !== null) {
                $state->per_person_price_cents = (int) $prices[$i]->roundHalfUpInt();
            }
            $state->weight_end_frac = $update['weights'][$i]->toFractionString();
            $state->weight_end = $update['weights'][$i]->toFloat();
            $state->flip_end = $update['flips'][$i];
            $state->save();
        }

        $round->update(['status' => 'completed', 'completed_at' => now()]);

        return [
            'update'      => $update,
            'occupancy'   => $occArr,
            'currColours' => $currColours,
        ];
    }

    private function advanceRound(BiddingSession $session, Round $round): Round
    {
        return DB::transaction(function () use ($session, $round) {
            $snap = $this->snapshotRound($session, $round);
            $rooms = $session->rooms->values();
            $delta = $this->delta($session);

            $newRound = Round::create([
                'bidding_session_id' => $session->id,
                'round_no'           => $round->round_no + 1,
                'status'             => 'active',
                'offset_unit'        => $session->offset_unit,
                'offset_percent'     => $session->offset_percent,
                'offset_fixed_cents' => $session->offset_fixed_cents,
                'delta_rate'         => $delta->toFloat(),
                'started_at'         => now(),
            ]);

            // Opening prices for the new round = derive at carried occupancy with new weights.
            $newWeights = $snap['update']['weights'];
            $carriedOcc = $snap['occupancy'];
            $openingPrices = array_sum($carriedOcc) > 0
                ? PricingEngine::derivePrices((int) $session->total_rent_cents, $newWeights, $carriedOcc)
                : null;

            foreach ($rooms as $i => $room) {
                RoomRoundState::create([
                    'round_id'               => $newRound->id,
                    'room_id'                => $room->id,
                    'weight_start'           => $newWeights[$i]->toFloat(),
                    'weight_start_frac'      => $newWeights[$i]->toFractionString(),
                    'flip_start'             => $snap['update']['flips'][$i],
                    'prev_colour'            => $snap['currColours'][$i]->value,
                    'occupancy'              => $carriedOcc[$i],
                    'per_person_price_cents' => $openingPrices ? (int) $openingPrices[$i]->roundHalfUpInt() : 0,
                ]);
            }

            // Carry each participant's room into the new round; they must re-confirm.
            foreach (Selection::where('round_id', $round->id)->get() as $sel) {
                Selection::create([
                    'round_id'       => $newRound->id,
                    'participant_id' => $sel->participant_id,
                    'room_id'        => $sel->room_id,
                    'state'          => 'pick',
                ]);
            }

            $session->update(['current_round_no' => $newRound->round_no]);

            return $newRound;
        });
    }

    // -----------------------------------------------------------------------
    // End Bid / settlement
    // -----------------------------------------------------------------------

    /**
     * Settle the session. Requires no red room unless $force (a cap-reached
     * force-end). Produces settlement lines summing to exactly R, ends the
     * session, revokes the invite, and mints the permanent result token.
     */
    public function endBid(BiddingSession $session, bool $force = false): Settlement
    {
        return DB::transaction(function () use ($session, $force) {
            $round = $session->currentRound();
            if (! $round) {
                throw new \RuntimeException('No active round to end.');
            }
            if (! $force && $this->anyRed($session, $round)) {
                throw new \RuntimeException('Cannot end: a room is over-subscribed.');
            }

            // Freeze the final round.
            if ($round->status === 'active') {
                $this->snapshotRound($session, $round);
            }

            $rooms = $session->rooms->keyBy('id');
            $occByRoom = $this->liveOccupancy($round);
            $weights = [];
            $occArr = [];
            $roomOrder = $session->rooms->values();
            foreach ($roomOrder as $room) {
                $weights[] = Rational::fromFractionString(
                    $round->roomStates()->where('room_id', $room->id)->value('weight_start_frac') ?? '1/1'
                );
                $occArr[] = (int) ($occByRoom[$room->id] ?? 0);
            }
            $pricesByIndex = array_sum($occArr) > 0
                ? PricingEngine::derivePrices((int) $session->total_rent_cents, $weights, $occArr)
                : [];
            $priceByRoom = [];
            foreach ($roomOrder as $i => $room) {
                $priceByRoom[$room->id] = $pricesByIndex[$i] ?? Rational::fromInt(0);
            }

            // Per-tenant exact amounts, in stable order (room position, then join order).
            $selections = Selection::where('round_id', $round->id)
                ->whereNotNull('room_id')
                ->get();
            $rows = $selections->map(function (Selection $s) use ($rooms) {
                return [
                    'participant_id' => $s->participant_id,
                    'room_id'        => $s->room_id,
                    'position'       => $rooms[$s->room_id]->position,
                ];
            })->sortBy([['position', 'asc'], ['participant_id', 'asc']])->values();

            $amounts = $rows->map(fn ($r) => $priceByRoom[$r['room_id']])->all();
            $rounded = SettlementCalc::largestRemainder($amounts, (int) $session->total_rent_cents);

            $settlement = Settlement::create([
                'bidding_session_id' => $session->id,
                'final_round_id'     => $round->id,
                'total_rent_cents'   => (int) $session->total_rent_cents,
            ]);
            foreach ($rows as $i => $r) {
                SettlementLine::create([
                    'settlement_id'  => $settlement->id,
                    'participant_id' => $r['participant_id'],
                    'room_id'        => $r['room_id'],
                    'amount_cents'   => $rounded[$i],
                ]);
            }

            $session->update([
                'status'       => 'ended',
                'ended_at'     => now(),
                'result_token' => $session->result_token ?? Str::random(48),
            ]);

            // Invite link expires immediately on End.
            if ($session->inviteLink) {
                $session->inviteLink->update(['revoked_at' => now()]);
            }

            // The paid session is consumed on End Bid (Part 3.7).
            app(\App\Domain\Billing\PaymentService::class)->consume($session);

            return $settlement;
        });
    }
}
