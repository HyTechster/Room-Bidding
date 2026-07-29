<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiddingSession extends Model
{
    protected $fillable = [
        'host_user_id',
        'total_rent_cents',
        'num_tenants',
        'num_rooms',
        'offset_unit',
        'offset_percent',
        'offset_fixed_cents',
        'currency',
        'total_capacity',
        'scope',
        'status',
        'current_round_no',
        'round_cap',
        'result_token',
        'expires_at',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'total_rent_cents'   => 'integer',
            'num_tenants'        => 'integer',
            'num_rooms'          => 'integer',
            'offset_percent'     => 'decimal:4',
            'offset_fixed_cents' => 'integer',
            'total_capacity'     => 'integer',
            'current_round_no'   => 'integer',
            'round_cap'          => 'integer',
            'expires_at'         => 'datetime',
            'started_at'         => 'datetime',
            'ended_at'           => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class)->orderBy('position');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class)->orderBy('join_order');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class)->orderBy('round_no');
    }
}
