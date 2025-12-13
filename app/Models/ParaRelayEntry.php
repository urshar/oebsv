<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ParaRelayEntry extends Model
{
    protected $table = 'para_relay_entries';

    protected $guarded = [];

    protected $casts = [
        'entry_time_ms' => 'integer',
    ];

    public function meet(): BelongsTo
    {
        return $this->belongsTo(ParaMeet::class, 'para_meet_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParaSession::class, 'para_session_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ParaEvent::class, 'para_event_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(ParaClub::class, 'para_club_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ParaRelayMember::class, 'para_relay_entry_id')
            ->orderBy('leg');
    }

    public function result(): HasOne
    {
        return $this->hasOne(ParaRelayResult::class, 'para_relay_entry_id');
    }
}
