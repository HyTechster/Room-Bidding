<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'bidding_session_id',
        'host_user_id',
        'provider',
        'amount_cents',
        'currency',
        'status',
        'external_ref',
        'paid_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_at'      => 'datetime',
            'consumed_at'  => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BiddingSession::class, 'bidding_session_id');
    }
}
