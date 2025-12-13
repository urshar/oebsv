<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParaRelaySplit extends Model
{
    protected $table = 'para_relay_splits';

    protected $guarded = [];

    protected $casts = [
        'distance' => 'integer',
        'cumulative_time_ms' => 'integer',
        'split_time_ms' => 'integer',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(ParaRelayResult::class, 'para_relay_result_id');
    }
}
