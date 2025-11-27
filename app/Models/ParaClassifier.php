<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParaClassifier extends Model
{
    use HasFactory;

    protected $table = 'para_classifiers';

    protected $guarded = [];

    public function classifications()
    {
        return $this->belongsToMany(
            ParaAthleteClassification::class,
            'para_athlete_classification_classifier',
            'para_classifier_id',
            'para_athlete_classification_id'
        )->withPivot('role')->withTimestamps();
    }

    // Komfort-Attribut: $classifier->full_name
    public function getFullNameAttribute(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
    }
}
