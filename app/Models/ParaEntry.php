<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParaEntry extends Model
{
    protected $table = 'para_entries';

    protected $guarded = [];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ParaEvent::class, 'para_event_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParaSession::class, 'para_session_id');
    }

    public function meet(): BelongsTo
    {
        return $this->belongsTo(ParaMeet::class, 'para_meet_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ParaResult::class, 'para_entry_id');
    }

    public function paraAthlete(): BelongsTo
    {
        return $this->athlete();
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(ParaAthlete::class, 'para_athlete_id');
    }

    // Falls irgendwo im Code bereits paraAthlete/paraClub verwendet wird,
    // führen wir die einfach auf die neuen Relationen zurück:

    public function paraClub(): BelongsTo
    {
        return $this->club();
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(ParaClub::class, 'para_club_id');
    }
}
