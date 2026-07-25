<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomRoundState extends Model
{
    protected $fillable = [
        'round_id',
        'room_id',
        'weight_start',
        'weight_start_frac',
        'weight_end',
        'weight_end_frac',
        'flip_start',
        'flip_end',
        'occupancy',
        'prev_colour',
        'colour',
        'per_person_price_cents',
    ];

    protected function casts(): array
    {
        return [
            'flip_start'             => 'integer',
            'flip_end'               => 'integer',
            'occupancy'              => 'integer',
            'per_person_price_cents' => 'integer',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
