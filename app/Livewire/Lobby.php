<?php

namespace App\Livewire;

use App\Events\SessionPing;
use App\Models\BiddingSession;
use App\Models\Participant;
use Livewire\Component;

/**
 * Shared lobby roster, used by both the host (SessionManage) and members
 * (JoinSession). Shows who has joined and their Ready state, applies anonymity,
 * and lets the viewer toggle their own Ready. Near-real-time via polling this
 * phase; Reverb push arrives in Phase 7.
 */
class Lobby extends Component
{
    public int $sessionId;
    public int $viewerParticipantId;

    public function mount(int $sessionId, int $viewerParticipantId): void
    {
        $this->sessionId = $sessionId;
        $this->viewerParticipantId = $viewerParticipantId;
    }

    public function getListeners(): array
    {
        return ['echo:session.'.$this->sessionId.',.SessionPing' => '$refresh'];
    }

    public function toggleReady(): void
    {
        $viewer = Participant::find($this->viewerParticipantId);
        if ($viewer && $viewer->bidding_session_id === $this->sessionId) {
            $viewer->is_ready = ! $viewer->is_ready;
            $viewer->save();
            rescue(fn () => SessionPing::dispatch($this->sessionId), report: false);
        }
    }

    /**
     * Name to show for $p from the viewer's perspective (spec 3.2 anonymity):
     *   - the viewer always sees themselves as "You";
     *   - names_visible sessions, or a host viewer, see real names;
     *   - otherwise members see "Member N".
     */
    public function displayNameFor(Participant $p, Participant $viewer): string
    {
        if ($p->id === $viewer->id) {
            return 'You';
        }
        $session = $this->session();
        if ($session->anonymity === 'names_visible' || $viewer->is_host) {
            return $p->display_name;
        }
        return 'Member '.$p->join_order;
    }

    public function session(): BiddingSession
    {
        return BiddingSession::findOrFail($this->sessionId);
    }

    public function render()
    {
        $session = $this->session();
        $participants = $session->activeParticipants()->get();
        $viewer = $participants->firstWhere('id', $this->viewerParticipantId)
            ?? Participant::findOrFail($this->viewerParticipantId);

        $readyCount = $participants->where('is_ready', true)->count();
        $hostReady = optional($participants->firstWhere('is_host', true))->is_ready ?? false;

        return view('livewire.lobby', [
            'session'      => $session,
            'participants' => $participants,
            'viewer'       => $viewer,
            'readyCount'   => $readyCount,
            'total'        => $participants->count(),
            'hostReady'    => $hostReady,
            'remaining'    => $session->remainingSlots(),
        ]);
    }
}
