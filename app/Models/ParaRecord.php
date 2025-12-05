<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParaRecord extends Model
{
    protected $fillable = [
        'para_athlete_id',
        'para_club_id',

        'record_list_name',
        'record_type',
        'course',
        'gender',
        'handicap',
        'sport_class',
        'nation_id',
        'recordlist_updated_at',

        'age_min',
        'age_max',
        'agegroup_code',

        'distance',
        'stroke',
        'relaycount',
        'swimtime_ms',

        'swum_at',
        'status',
        'meet_name',
        'meet_nation',

        'holder_firstname',
        'holder_lastname',
        'holder_year_of_birth',

        'is_relay',
    ];

    protected $casts = [
        'recordlist_updated_at' => 'date',
        'swum_at'               => 'date',
        'is_relay'              => 'bool',
    ];

    public function splits(): HasMany
    {
        return $this->hasMany(ParaRecordSplit::class);
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(ParaAthlete::class, 'para_athlete_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(ParaClub::class, 'para_club_id');
    }

    public function getHolderNameAttribute(): string
    {
        return trim(($this->holder_firstname ?? '') . ' ' . ($this->holder_lastname ?? ''));
    }

    public function nation(): BelongsTo {
        return $this->belongsTo(Nation::class, 'nation_id');
    }
}
