<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'anonymity',
        'currency',
        'total_capacity',
        'scope',
        'status',
        'current_round_no',
        'round_cap',
        'result_token',
        'expires_at',
        'paid_at',
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
            'paid_at'            => 'datetime',
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

    public function activeParticipants(): HasMany
    {
        return $this->participants()->whereNull('removed_at');
    }

    public function inviteLink(): HasOne
    {
        return $this->hasOne(InviteLink::class);
    }

    public function hostParticipant(): HasOne
    {
        return $this->hasOne(Participant::class)->where('is_host', true);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class)->orderBy('round_no');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function currentRound(): ?Round
    {
        return $this->rounds()->where('round_no', $this->current_round_no)->first();
    }

    /** Remaining tenant slots (host counts toward num_tenants). */
    public function remainingSlots(): int
    {
        return max(0, $this->num_tenants - $this->activeParticipants()->count());
    }
}
