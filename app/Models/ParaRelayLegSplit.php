<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParaRelayLegSplit extends Model
{
    protected $table = 'para_relay_leg_splits';

    protected $guarded = [];

    protected $casts = [
        'distance_in_leg' => 'integer',
        'cumulative_time_ms' => 'integer',
        'split_time_ms' => 'integer',
        'absolute_distance' => 'integer',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(ParaRelayMember::class, 'para_relay_member_id');
    }
}
