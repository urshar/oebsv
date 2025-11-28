<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ParaClassifier extends Model
{
    protected $fillable = [
        'firstName',
        'lastName',
        'email',
        'phone',
        'type',     // TECH, MED, BOTH
        'wps_id',
        'nation_id',
    ];

    protected $appends = ['fullName'];

    public function getFullNameAttribute(): string
    {
        return trim(($this->lastName ?? '') . ' ' . ($this->firstName ?? ''));
    }

    // TECH oder BOTH
    public function scopeTechnical($query)
    {
        return $query->whereIn('type', ['TECH', 'BOTH']);
    }

    // MED oder BOTH
    public function scopeMedical($query)
    {
        return $query->whereIn('type', ['MED', 'BOTH']);
    }

    public function nation(): BelongsTo
    {
        return $this->belongsTo(Nation::class, 'nation_id');
    }

    public function athleteClassifications(): BelongsToMany
    {
        return $this->belongsToMany(ParaAthleteClassification::class,
            'para_athlete_classification_classifier')
            ->withPivot('role')
            ->withTimestamps();
    }
}
