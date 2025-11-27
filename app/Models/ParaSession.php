<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ParaMeet;
use App\Models\ParaEvent;
use App\Models\ParaEntry;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParaSession extends Model
{
    use HasFactory;

    protected $table = 'para_sessions';

    protected $guarded = [];

    protected $casts = [
        'date'               => 'date',
        'start_time'         => 'datetime:H:i',
        'warmup_from'        => 'datetime:H:i',
        'warmup_until'       => 'datetime:H:i',
        'official_meeting'   => 'datetime:H:i',
        'teamleader_meeting' => 'datetime:H:i',
    ];

    public function meet(): BelongsTo
    {
        return $this->belongsTo(ParaMeet::class, 'para_meet_id');
    }

    // WICHTIG: diese Relation fehlt aktuell bei dir
    public function events(): HasMany|ParaSession
    {
        return $this->hasMany(ParaEvent::class, 'para_session_id')
            ->orderBy('order');
    }

    public function entries(): HasMany|ParaSession
    {
        return $this->hasMany(ParaEntry::class, 'para_session_id');
    }
}
