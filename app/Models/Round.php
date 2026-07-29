<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Round extends Model
{
    protected $fillable = [
        'bidding_session_id',
        'round_no',
        'status',
        'offset_unit',
        'offset_percent',
        'offset_fixed_cents',
        'delta_rate',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'round_no'           => 'integer',
            'offset_percent'     => 'decimal:4',
            'offset_fixed_cents' => 'integer',
            'started_at'         => 'datetime',
            'completed_at'       => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BiddingSession::class, 'bidding_session_id');
    }

    public function roomStates(): HasMany
    {
        return $this->hasMany(RoomRoundState::class);
    }
}
