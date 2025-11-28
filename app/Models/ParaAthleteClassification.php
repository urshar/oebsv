<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ParaAthleteClassification extends Model
{
    protected $fillable = [
        'para_athlete_id',
        'classification_date',
        'location',
        'is_international',
        'wps_license',
        'sportclass_s',
        'sportclass_sb',
        'sportclass_sm',
        'sportclass_exception',
        'status',
        'tech_classifier_1',   // alte String-Felder – kannst du später entfernen
        'tech_classifier_2',
        'med_classifier',
        'notes',
    ];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(ParaAthlete::class, 'para_athlete_id');
    }

    public function classifiers(): BelongsToMany
    {
        return $this->belongsToMany(ParaClassifier::class,
            'para_athlete_classification_classifier')
            ->withPivot('role')
            ->withTimestamps();
    }

    // Praktische Helper:
    public function classifierByRole(string $role): ?ParaClassifier
    {
        return $this->classifiers->firstWhere('pivot.role', $role);
    }

    public function getTechClassifier1Attribute(): ?ParaClassifier
    {
        return $this->classifierByRole('TECH1');
    }

    public function getTechClassifier2Attribute(): ?ParaClassifier
    {
        return $this->classifierByRole('TECH2');
    }

    public function getMedClassifierModelAttribute(): ?ParaClassifier
    {
        return $this->classifierByRole('MED');
    }
}
