<?php

namespace App\Livewire;

use App\Domain\Bidding\RoundService;
use App\Domain\Pricing\Colour;
use App\Events\SessionPing;
use App\Models\BiddingSession;
use App\Models\Participant;
use App\Models\Selection;
use Livewire\Component;

class BiddingRoom extends Component
{
    public int $sessionId;
    public int $viewerParticipantId;

    public function mount(int $sessionId, int $viewerParticipantId): void
    {
        $this->sessionId = $sessionId;
        $this->viewerParticipantId = $viewerParticipantId;
    }

    /** Live updates: refresh whenever anyone in this session changes something. */
    public function getListeners(): array
    {
        return ['echo:session.'.$this->sessionId.',.SessionPing' => '$refresh'];
    }

    private function ping(): void
    {
        // Never let a broadcasting hiccup break the action; polling is the fallback.
        rescue(fn () => SessionPing::dispatch($this->sessionId), report: false);
    }

    private function session(): BiddingSession
    {
        return BiddingSession::with('rooms')->findOrFail($this->sessionId);
    }

    private function viewer(): Participant
    {
        return Participant::findOrFail($this->viewerParticipantId);
    }

    /** The viewer's current selection in the active round (or null). */
    private function mySelection(?int $roundId): ?Selection
    {
        if (! $roundId) {
            return null;
        }
        return Selection::where('round_id', $roundId)
            ->where('participant_id', $this->viewerParticipantId)
            ->first();
    }

    // ---- state machine ----------------------------------------------------

    public function pickRoom(int $roomId): void
    {
        $session = $this->session();
        $round = $session->currentRound();
        if (! $this->canAct($session, $round)) {
            return;
        }
        // Room must belong to this session.
        if (! $session->rooms->contains('id', $roomId)) {
            return;
        }
        $sel = Selection::firstOrNew([
            'round_id'       => $round->id,
            'participant_id' => $this->viewerParticipantId,
        ]);
        if ($sel->state === 'confirm') {
            return; // final for this round
        }
        $sel->room_id = $roomId;
        $sel->state = 'pick';
        $sel->save();
        $this->ping();
    }

    public function lock(): void
    {
        $this->transition(fn (Selection $s) => $s->room_id !== null && $s->state === 'pick', 'lock');
    }

    public function unlock(): void
    {
        $this->transition(fn (Selection $s) => $s->state === 'lock', 'pick');
    }

    public function confirm(): void
    {
        $session = $this->session();
        $round = $session->currentRound();
        if (! $this->canAct($session, $round)) {
            return;
        }
        $sel = $this->mySelection($round->id);
        if (! $sel || $sel->room_id === null || $sel->state === 'confirm') {
            return;
        }
        $sel->state = 'confirm';
        $sel->confirmed_at = now();
        $sel->save();

        // If everyone (host included) has now confirmed and any room is red, the
        // system advances automatically (spec 3.4). No red -> host may End Bid.
        app(RoundService::class)->maybeAdvanceOrHold($this->session());

        $this->ping();
    }

    private function transition(callable $guard, string $toState): void
    {
        $session = $this->session();
        $round = $session->currentRound();
        if (! $this->canAct($session, $round)) {
            return;
        }
        $sel = $this->mySelection($round->id);
        if ($sel && $guard($sel)) {
            $sel->state = $toState;
            $sel->save();
            $this->ping();
        }
    }

    private function canAct(BiddingSession $session, $round): bool
    {
        return $session->status === 'bidding' && $round && $round->status === 'active';
    }

    // ---- anonymity --------------------------------------------------------

    private function displayNameFor(Participant $p, Participant $viewer, BiddingSession $session): string
    {
        if ($p->id === $viewer->id) {
            return 'You';
        }
        // Once round 1 starts, even the host is anonymised (safeguard 3.2).
        if ($session->anonymity === 'names_visible') {
            return $p->display_name;
        }
        return 'Member '.$p->join_order;
    }

    public function render()
    {
        $session = $this->session();
        $round = $session->currentRound();
        $viewer = $this->viewer();
        $service = app(RoundService::class);

        $roomData = $round ? $service->liveRoomData($session, $round) : [];
        $mySel = $this->mySelection($round?->id);

        $participants = $session->activeParticipants()->get();
        $selections = $round
            ? Selection::where('round_id', $round->id)->get()->keyBy('participant_id')
            : collect();

        $roster = $participants->map(function (Participant $p) use ($selections, $viewer, $session) {
            $s = $selections->get($p->id);
            return [
                'name'    => $this->displayNameFor($p, $viewer, $session),
                'is_host' => $p->is_host,
                'is_you'  => $p->id === $viewer->id,
                'state'   => $s?->state,          // pick | lock | confirm | null
            ];
        });

        $confirmedCount = $selections->where('state', 'confirm')->count();
        $totalActive = $participants->count();
        $anyRed = collect($roomData)->contains(fn ($d) => $d['colour'] === Colour::Red);

        $myPriceCents = ($mySel && $mySel->room_id && isset($roomData[$mySel->room_id]))
            ? $roomData[$mySel->room_id]['price_cents']
            : null;

        return view('livewire.bidding-room', [
            'session'        => $session,
            'round'          => $round,
            'rooms'          => $session->rooms,
            'roomData'       => $roomData,
            'mySel'          => $mySel,
            'roster'         => $roster,
            'confirmedCount' => $confirmedCount,
            'totalActive'    => $totalActive,
            'allConfirmed'   => $totalActive > 0 && $confirmedCount === $totalActive,
            'anyRed'         => $anyRed,
            'myPriceCents'   => $myPriceCents,
        ]);
    }
}
