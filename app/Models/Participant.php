<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Participant extends Model
{
    protected $fillable = [
        'bidding_session_id',
        'user_id',
        'is_host',
        'display_name',
        'join_order',
        'participant_token',
        'status',
        'is_ready',
        'last_seen_at',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_host'      => 'boolean',
            'is_ready'     => 'boolean',
            'join_order'   => 'integer',
            'last_seen_at' => 'datetime',
            'removed_at'   => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BiddingSession::class, 'bidding_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
