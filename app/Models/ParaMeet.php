<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ParaMeet extends Model
{
    protected $table = 'para_meets';

    protected $guarded = [];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'entry_start_date' => 'date',
        'entry_deadline' => 'date',
        'withdraw_until' => 'date',
        'lenex_revisiondate' => 'date',
        'lenex_created' => 'datetime',
    ];

    public function nation(): BelongsTo
    {
        return $this->belongsTo(Nation::class, 'nation_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ParaSession::class, 'para_meet_id')->orderBy('date')->orderBy('number');
    }

    public function events(): HasManyThrough
    {
        return $this->hasManyThrough(
            ParaEvent::class,
            ParaSession::class,
            'para_meet_id',
            'para_session_id',
            'id',
            'id'
        );
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ParaEntry::class, 'para_meet_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ParaResult::class, 'para_meet_id');
    }

    public function relayEntries(): HasMany
    {
        return $this->hasMany(ParaRelayEntry::class, 'para_meet_id');
    }

}
