<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InviteLink extends Model
{
    protected $fillable = [
        'bidding_session_id',
        'token',
        'max_uses',
        'uses_count',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'max_uses'   => 'integer',
            'uses_count' => 'integer',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BiddingSession::class, 'bidding_session_id');
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return false;
        }
        return true;
    }
}
