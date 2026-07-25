<?php

namespace App\Livewire;

use App\Domain\Bidding\RoundService;
use App\Domain\Billing\PaymentService;
use App\Domain\Pricing\Colour;
use App\Events\SessionPing;
use App\Models\BiddingSession;
use App\Models\InviteLink;
use App\Models\Selection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SessionManage extends Component
{
    public int $sessionId;

    public function mount(BiddingSession $session): void
    {
        abort_unless($session->host_user_id === Auth::id(), 403);
        $this->sessionId = $session->id;

        if ($this->isExpired($session)) {
            return; // don't advance or create links for an expired session
        }

        if ($session->status === 'draft') {
            $session->update(['status' => 'lobby']);
        }
        $this->ensureInviteLink($session);
    }

    private function isExpired(BiddingSession $session): bool
    {
        return $session->status === 'expired'
            || ($session->status !== 'ended' && $session->expires_at?->isPast());
    }

    private function ensureInviteLink(BiddingSession $session): void
    {
        if ($session->inviteLink()->exists()) {
            return;
        }
        InviteLink::create([
            'bidding_session_id' => $session->id,
            'token'              => Str::random(40),
            'max_uses'           => max(0, $session->num_tenants - 1),
            'uses_count'         => 0,
            'expires_at'         => $session->expires_at,
        ]);
    }

    public function startBid(): void
    {
        $session = BiddingSession::with('rooms')->findOrFail($this->sessionId);
        abort_unless($session->host_user_id === Auth::id(), 403);

        if ($session->status !== 'lobby') {
            return;
        }

        if (! app(PaymentService::class)->isPaid($session)) {
            session()->flash('start_error', 'This session is locked — pay to unlock it before starting.');
            return;
        }

        $active = $session->activeParticipants()->get();
        $allJoined = $active->count() === $session->num_tenants;
        $allReady = $active->count() > 0 && $active->every(fn ($p) => $p->is_ready);

        if (! $allJoined || ! $allReady) {
            session()->flash('start_error', 'Everyone must join and be ready before you can start.');
            return;
        }

        app(RoundService::class)->startFirstRound($session);
        rescue(fn () => SessionPing::dispatch($session->id), report: false);
    }

    public function pay(): void
    {
        $session = BiddingSession::findOrFail($this->sessionId);
        abort_unless($session->host_user_id === Auth::id(), 403);
        if ($this->isExpired($session)) {
            return;
        }

        app(PaymentService::class)->pay($session);
        rescue(fn () => SessionPing::dispatch($session->id), report: false);
    }

    public function endBid(): void
    {
        $this->doEnd(false);
    }

    public function forceEnd(): void
    {
        $this->doEnd(true);
    }

    private function doEnd(bool $force): void
    {
        $session = BiddingSession::with('rooms')->findOrFail($this->sessionId);
        abort_unless($session->host_user_id === Auth::id(), 403);

        if (! in_array($session->status, ['bidding', 'cap_reached'], true)) {
            return;
        }

        try {
            app(RoundService::class)->endBid($session, $force);
        } catch (\Throwable $e) {
            session()->flash('start_error', $e->getMessage());
            return;
        }
        rescue(fn () => SessionPing::dispatch($session->id), report: false);
    }

    public function getListeners(): array
    {
        return ['echo:session.'.$this->sessionId.',.SessionPing' => '$refresh'];
    }

    public function render()
    {
        $session = BiddingSession::with(['rooms', 'inviteLink', 'hostParticipant'])->findOrFail($this->sessionId);
        $invite = $session->inviteLink;

        // Lobby gating.
        $active = $session->activeParticipants()->get();
        $allJoined = $active->count() === $session->num_tenants;
        $allReady = $active->count() > 0 && $active->every(fn ($p) => $p->is_ready);
        $canStart = $allJoined && $allReady;

        // Bidding gating (for the host control strip — Proceed/End wired in Phase 8).
        $allConfirmed = false;
        $anyRed = false;
        if ($session->status === 'bidding' && ($round = $session->currentRound())) {
            $selections = Selection::where('round_id', $round->id)->get();
            $allConfirmed = $active->count() > 0
                && $selections->where('state', 'confirm')->count() === $active->count();
            $roomData = app(RoundService::class)->liveRoomData($session, $round);
            $anyRed = collect($roomData)->contains(fn ($d) => $d['colour'] === Colour::Red);
        }

        $settlement = null;
        if ($session->status === 'ended') {
            $settlement = \App\Models\Settlement::with(['lines.participant', 'lines.room'])
                ->where('bidding_session_id', $session->id)->first();
        }

        $payment = app(PaymentService::class);
        $isPaid = $payment->isPaid($session);
        $isExpired = $this->isExpired($session);

        return view('livewire.session-manage', [
            'session'      => $session,
            'isExpired'    => $isExpired,
            'invite'       => $invite,
            'joinUrl'      => $invite ? route('join', $invite->token) : null,
            'hostPid'      => optional($session->hostParticipant)->id,
            'isPaid'       => $isPaid,
            'priceCents'   => $payment->priceCents(),
            'currency'     => $payment->currency(),
            'canStart'     => $canStart && $isPaid,
            'joinedCount'  => $active->count(),
            'readyCount'   => $active->where('is_ready', true)->count(),
            'allConfirmed' => $allConfirmed,
            'anyRed'       => $anyRed,
            'canEnd'       => $session->status === 'bidding' && $allConfirmed && ! $anyRed,
            'capReached'   => $session->status === 'cap_reached',
            'settlement'   => $settlement,
        ]);
    }
}
