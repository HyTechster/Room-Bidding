<?php

namespace App\Livewire;

use App\Domain\Pricing\Rational;
use App\Models\BiddingSession;
use App\Models\Settlement;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class ResultPage extends Component
{
    public string $token;

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function render()
    {
        $session = BiddingSession::with('rooms')
            ->where('result_token', $this->token)
            ->where('status', 'ended')
            ->first();

        if (! $session) {
            return view('livewire.result-page', ['session' => null]);
        }

        $rooms = $session->rooms; // ordered by position
        $roomsById = $rooms->keyBy('id');

        $settlement = Settlement::with(['lines.participant', 'lines.room'])
            ->where('bidding_session_id', $session->id)
            ->first();

        // Per-person rows (real names revealed), ordered by room position then join order.
        $lines = $settlement
            ? $settlement->lines->sortBy([
                fn ($l) => $roomsById[$l->room_id]->position,
                fn ($l) => $l->participant->join_order,
            ])->values()
            : collect();

        $total = (int) $lines->sum('amount_cents');

        // Per-room totals from the final round.
        $finalStates = $settlement
            ? \App\Models\RoomRoundState::where('round_id', $settlement->final_round_id)->get()->keyBy('room_id')
            : collect();

        $roomTotals = $rooms->map(function ($room) use ($lines, $finalStates) {
            $roomLines = $lines->where('room_id', $room->id);
            return [
                'label'       => $room->label ?: 'Room '.($room->position + 1),
                'occupancy'   => $roomLines->count(),
                'capacity'    => $room->max_occupants,
                'price_cents' => (int) ($finalStates[$room->id]->per_person_price_cents ?? 0),
                'total_cents' => (int) $roomLines->sum('amount_cents'),
            ];
        });

        // Round-by-round history for audit/replay.
        $history = $session->rounds()->with('roomStates')->orderBy('round_no')->get()->map(function ($round) use ($rooms) {
            $states = $round->roomStates->keyBy('room_id');
            return [
                'round_no' => $round->round_no,
                'rooms'    => $rooms->map(function ($room) use ($states) {
                    $s = $states[$room->id] ?? null;
                    $weight = $s && $s->weight_start_frac
                        ? round(Rational::fromFractionString($s->weight_start_frac)->toFloat(), 4)
                        : 1.0;
                    return [
                        'label'       => $room->label ?: 'Room '.($room->position + 1),
                        'occupancy'   => $s->occupancy ?? 0,
                        'capacity'    => $room->max_occupants,
                        'colour'      => $s->colour ?? null,
                        'price_cents' => (int) ($s->per_person_price_cents ?? 0),
                        'weight'      => $weight,
                    ];
                })->all(),
            ];
        });

        return view('livewire.result-page', [
            'session'    => $session,
            'lines'      => $lines,
            'total'      => $total,
            'roomTotals' => $roomTotals,
            'history'    => $history,
            'balanced'   => $total === (int) $session->total_rent_cents,
            'symbol'     => \App\Support\Currency::symbol($session->currency),
        ]);
    }
}
