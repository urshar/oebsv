<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParaRelayMember extends Model
{
    protected $table = 'para_relay_members';

    protected $guarded = [];

    protected $casts = [
        'leg' => 'integer',
        'leg_time_ms' => 'integer',
        'leg_distance' => 'integer',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ParaRelayEntry::class, 'para_relay_entry_id');
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(ParaAthlete::class, 'para_athlete_id');
    }

    /** Splits innerhalb der Leg (kumuliert innerhalb der Leg) */
    public function legSplits(): HasMany
    {
        return $this->hasMany(ParaRelayLegSplit::class, 'para_relay_member_id')
            ->orderBy('distance_in_leg');
    }
}
