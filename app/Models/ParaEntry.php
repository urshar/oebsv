<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParaEntry extends Model
{

    protected $table = 'para_entries';

    protected $guarded = [];

    protected $casts = [
        'qualifying_date' => 'date',
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

    public function agegroup(): BelongsTo
    {
        return $this->belongsTo(ParaEventAgegroup::class, 'para_event_agegroup_id');
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(ParaAthlete::class, 'para_athlete_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(ParaClub::class, 'para_club_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ParaResult::class, 'para_entry_id');
    }
}
