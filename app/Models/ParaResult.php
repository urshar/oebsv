<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParaResult extends Model
{
    protected $table = 'para_results';

    protected $guarded = [];

    protected $casts = [
        'time_ms'          => 'integer',
        'reaction_time_ms' => 'integer',
        'rank'             => 'integer',
        'heat'             => 'integer',
        'lane'             => 'integer',
        'points'           => 'integer',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ParaEntry::class, 'para_entry_id');
    }

    public function meet(): BelongsTo
    {
        return $this->belongsTo(ParaMeet::class, 'para_meet_id');
    }

    public function splits(): HasMany
    {
        return $this->hasMany(ParaSplit::class, 'para_result_id')
            ->orderBy('distance');
    }

    public function getTimeFormattedAttribute(): ?string
    {
        if ($this->time_ms === null) {
            return null;
        }

        $totalMs = $this->time_ms;
        $totalSeconds = intdiv($totalMs, 1000);
        $ms = $totalMs % 1000;

        $minutes = intdiv($totalSeconds, 60);
        $seconds = $totalSeconds % 60;

        // mm:ss,cc (Hundertstel)
        $centis = intdiv($ms, 10);

        return sprintf('%d:%02d,%02d', $minutes, $seconds, $centis);
    }
}
