<?php

namespace App\Domain\Billing;

use App\Models\BiddingSession;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Payment gate for running a session (Part 3.7).
 *
 * Provider is deferred (Q2): {@see pay()} is a sandbox that marks the session paid
 * without charging. A real provider replaces the body of pay()/adds a webhook that
 * transitions the Payment to 'paid' — the rest of the app only asks {@see isPaid()}
 * and {@see consume()}, so the UI and orchestration need no changes.
 */
class PaymentService
{
    public function priceCents(): int
    {
        return (int) config('billing.session_price_cents', 1000);
    }

    public function currency(): string
    {
        return (string) config('billing.currency', 'MYR');
    }

    /** A session is unlocked once it has a 'paid' payment (whether or not later consumed). */
    public function isPaid(BiddingSession $session): bool
    {
        return $session->payments()->where('status', 'paid')->exists();
    }

    /** Sandbox payment: create a paid Payment and stamp the session. Idempotent. */
    public function pay(BiddingSession $session): Payment
    {
        return DB::transaction(function () use ($session) {
            $existing = $session->payments()->where('status', 'paid')->first();
            if ($existing) {
                return $existing;
            }

            $payment = Payment::create([
                'bidding_session_id' => $session->id,
                'host_user_id'       => $session->host_user_id,
                'provider'           => config('billing.provider', 'stub'),
                'amount_cents'       => $this->priceCents(),
                'currency'           => $this->currency(),
                'status'             => 'paid',
                'external_ref'       => 'sandbox-'.uniqid(),
                'paid_at'            => now(),
            ]);

            $session->update(['paid_at' => now()]);

            return $payment;
        });
    }

    /** Consume the paid session on End Bid (spec 3.7): it cannot be re-used. */
    public function consume(BiddingSession $session): void
    {
        $session->payments()
            ->where('status', 'paid')
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }
}
