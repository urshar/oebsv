<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParaRelayResult extends Model
{
    protected $table = 'para_relay_results';

    protected $guarded = [];

    protected $casts = [
        'time_ms' => 'integer',
        'rank' => 'integer',
        'heat' => 'integer',
        'lane' => 'integer',
        'points' => 'integer',
    ];

    public function meet(): BelongsTo
    {
        return $this->belongsTo(ParaMeet::class, 'para_meet_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ParaRelayEntry::class, 'para_relay_entry_id');
    }

    /** Team-Splits (kumuliert ab Start) */
    public function splits(): HasMany
    {
        return $this->hasMany(ParaRelaySplit::class, 'para_relay_result_id')
            ->orderBy('distance');
    }
}
