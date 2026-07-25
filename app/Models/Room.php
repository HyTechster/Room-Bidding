<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    protected $fillable = [
        'bidding_session_id',
        'position',
        'label',
        'max_occupants',
    ];

    protected function casts(): array
    {
        return [
            'position'      => 'integer',
            'max_occupants' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BiddingSession::class, 'bidding_session_id');
    }
}
