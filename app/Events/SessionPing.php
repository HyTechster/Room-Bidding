<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A content-free "something changed in this session" ping, broadcast on the
 * public per-session channel. Clients react by refreshing their Livewire
 * component, which re-renders with anonymity applied server-side — so no
 * participant data (and no name mapping) is ever put on the wire.
 *
 * Broadcasts synchronously (ShouldBroadcastNow) so no queue worker is required.
 */
class SessionPing implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $sessionId) {}

    public function broadcastOn(): Channel
    {
        return new Channel('session.'.$this->sessionId);
    }

    public function broadcastAs(): string
    {
        return 'SessionPing';
    }

    public function broadcastWith(): array
    {
        return ['at' => now()->toIso8601String()];
    }
}
