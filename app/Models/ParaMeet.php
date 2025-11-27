<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ParaMeet extends Model
{
    use HasFactory;

    protected $table = 'para_meets';

    protected $guarded = [];

    protected $casts = [
        'from_date'         => 'date',
        'to_date'           => 'date',
        'entry_start_date'  => 'date',
        'entry_deadline'    => 'date',
        'withdraw_until'    => 'date',
        'lenex_revisiondate'=> 'date',
        'lenex_created'     => 'datetime',
    ];

    public function nation(): BelongsTo
    {
        return $this->belongsTo(Nation::class, 'nation_id');
    }

    public function sessions(): HasMany|ParaMeet
    {
        return $this->hasMany(ParaSession::class, 'para_meet_id')->orderBy('date')->orderBy('number');
    }

    public function events(): HasManyThrough|ParaMeet
    {
        // alle Events des Meetings über Sessions
        return $this->hasManyThrough(
            ParaEvent::class,
            ParaSession::class,
            'para_meet_id',    // FK auf ParaMeet in para_sessions
            'para_session_id', // FK auf Session in para_events
            'id',
            'id'
        );
    }

    public function entries()
    {
        return $this->hasMany(ParaEntry::class, 'para_meet_id');
    }

}
