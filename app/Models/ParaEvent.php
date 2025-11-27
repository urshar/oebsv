<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class ParaEvent extends Model
{
    use HasFactory;

    protected $table = 'para_events';

    protected $guarded = [];

    protected $casts = [
        'fee' => 'float',
        // 'is_relay' -> kommt jetzt aus $this->swimstyle?->is_relay
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParaSession::class, 'para_session_id');
    }

    public function meet(): HasOneThrough|ParaEvent
    {
        return $this->hasOneThrough(
            ParaMeet::class,
            ParaSession::class,
            'id',
            'id',
            'para_session_id',
            'para_meet_id'
        );
    }

    public function agegroups(): HasMany|ParaEvent
    {
        return $this->hasMany(ParaEventAgegroup::class, 'para_event_id');
    }

    public function swimstyle(): BelongsTo
    {
        return $this->belongsTo(Swimstyle::class, 'swimstyle_id');
    }

    // Komfort-Accessors, falls du alte Felder weiter nutzen willst:
    public function getDistanceAttribute(): ?int
    {
        return $this->swimstyle?->distance;
    }

    public function getStrokeAttribute(): ?string
    {
        return $this->swimstyle?->stroke;
    }

    public function getRelaycountAttribute(): ?int
    {
        return $this->swimstyle?->relaycount;
    }

    public function getIsRelayAttribute(): bool
    {
        return (bool) ($this->swimstyle?->is_relay);
    }

    public function entries(): HasMany|ParaEvent
    {
        return $this->hasMany(ParaEntry::class, 'para_event_id');
    }
}
