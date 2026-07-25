<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementLine extends Model
{
    protected $fillable = [
        'settlement_id',
        'participant_id',
        'room_id',
        'amount_cents',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
