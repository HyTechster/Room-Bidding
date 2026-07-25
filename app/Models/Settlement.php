<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends Model
{
    protected $fillable = [
        'bidding_session_id',
        'final_round_id',
        'total_rent_cents',
    ];

    protected function casts(): array
    {
        return ['total_rent_cents' => 'integer'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BiddingSession::class, 'bidding_session_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SettlementLine::class);
    }
}
