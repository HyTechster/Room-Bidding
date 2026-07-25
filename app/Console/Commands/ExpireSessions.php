<?php

namespace App\Console\Commands;

use App\Models\BiddingSession;
use App\Models\InviteLink;
use Illuminate\Console\Command;

/**
 * Lifecycle cleanup (Part 3.6 / A7): a session's invite link expires 7 days after
 * creation. This command marks stale, unfinished sessions as 'expired' and revokes
 * their invite links. Ended sessions are never touched — their results are kept
 * permanently. Safe to run repeatedly (idempotent).
 */
class ExpireSessions extends Command
{
    protected $signature = 'sessions:expire';

    protected $description = 'Expire stale unfinished sessions and revoke their invite links';

    public function handle(): int
    {
        $now = now();

        $sessions = BiddingSession::whereNotIn('status', ['ended', 'expired'])
            ->where('expires_at', '<', $now)
            ->update(['status' => 'expired', 'updated_at' => $now]);

        $links = InviteLink::whereNull('revoked_at')
            ->where('expires_at', '<', $now)
            ->update(['revoked_at' => $now]);

        $this->info("Expired {$sessions} session(s); revoked {$links} invite link(s).");

        return self::SUCCESS;
    }
}
