<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParaResult extends Model
{
    protected $table = 'para_results';

    protected $guarded = [];

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

    // Hilfs-Accessor: Zeit im mm:ss,cc Format
    public function getTimeFormattedAttribute(): ?string
    {
        if (!$this->time_ms) {
            return null;
        }

        $totalCentis = intdiv($this->time_ms, 10); // ms → Zentelsekunden
        $centis = $totalCentis % 100;
        $seconds = intdiv($totalCentis, 100);
        $minutes = intdiv($seconds, 60);
        $seconds = $seconds % 60;

        return sprintf('%d:%02d,%02d', $minutes, $seconds, $centis);
    }
}
