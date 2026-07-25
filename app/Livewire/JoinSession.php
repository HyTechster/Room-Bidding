<?php

namespace App\Livewire;

use App\Events\SessionPing;
use App\Models\BiddingSession;
use App\Models\InviteLink;
use App\Models\Participant;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class JoinSession extends Component
{
    public string $token;
    public ?int $sessionId = null;
    public bool $joined = false;
    public ?int $viewerParticipantId = null;

    public string $display_name = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $invite = InviteLink::where('token', $token)->first();
        if (! $invite) {
            return; // render shows "invalid link"
        }
        $this->sessionId = $invite->bidding_session_id;

        // Returning member on this browser?
        $existingToken = request()->cookie($this->cookieName());
        if ($existingToken) {
            $p = Participant::where('bidding_session_id', $this->sessionId)
                ->where('participant_token', $existingToken)
                ->whereNull('removed_at')
                ->first();
            if ($p) {
                $this->joined = true;
                $this->viewerParticipantId = $p->id;
            }
        }
    }

    public function confirmJoin()
    {
        if ($this->joined) {
            return;
        }

        // Rate-limit join attempts per IP to deter abuse of public invite links.
        $key = 'join:'.request()->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('display_name', 'Too many attempts. Please wait a minute and try again.');
            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($key, 60);

        $invite = InviteLink::where('token', $this->token)->firstOrFail();
        $session = BiddingSession::findOrFail($invite->bidding_session_id);

        if (! in_array($session->status, ['draft', 'lobby'], true)) {
            $this->addError('display_name', 'Bidding has already started — this link is closed.');
            return;
        }
        if (! $invite->isUsable() || $session->remainingSlots() <= 0) {
            $this->addError('display_name', 'This session is already full or the link has expired.');
            return;
        }

        $this->validate([
            'display_name' => ['required', 'string', 'max:100'],
        ]);

        $participantToken = Str::random(40);

        $participant = DB::transaction(function () use ($session, $invite, $participantToken) {
            $nextOrder = (int) $session->participants()->max('join_order') + 1;

            $p = Participant::create([
                'bidding_session_id' => $session->id,
                'user_id'            => null,
                'is_host'            => false,
                'display_name'       => trim($this->display_name),
                'join_order'         => $nextOrder,
                'participant_token'  => $participantToken,
                'status'             => 'joined',
                'is_ready'           => false,
            ]);

            $invite->increment('uses_count');

            return $p;
        });

        Cookie::queue($this->cookieName(), $participantToken, 60 * 24 * 7); // 7 days

        rescue(fn () => SessionPing::dispatch($session->id), report: false);

        $this->joined = true;
        $this->viewerParticipantId = $participant->id;
    }

    public function getListeners(): array
    {
        return $this->sessionId
            ? ['echo:session.'.$this->sessionId.',.SessionPing' => '$refresh']
            : [];
    }

    private function cookieName(): string
    {
        return 'rb_participant_'.$this->sessionId;
    }

    public function render()
    {
        $invite = $this->sessionId ? InviteLink::where('token', $this->token)->first() : null;
        $session = $this->sessionId ? BiddingSession::find($this->sessionId) : null;

        $tooLate = $session && ! in_array($session->status, ['draft', 'lobby'], true);
        $full = $session && $session->remainingSlots() <= 0;

        return view('livewire.join-session', [
            'invite'  => $invite,
            'session' => $session,
            'tooLate' => $tooLate,
            'full'    => $full,
        ]);
    }
}
