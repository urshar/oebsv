<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParaAthleteClassification extends Model
{
    use HasFactory;

    protected $table = 'para_athlete_classifications';

    protected $guarded = [];

    protected $casts = [
        'classification_date' => 'date',
        'is_international'    => 'boolean',
    ];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(ParaAthlete::class, 'para_athlete_id');
    }

    /**
     * Nach dem Speichern / Löschen die Athleten-Klassenfelder aktualisieren.
     */
    protected static function booted(): void
    {
        static::saved(function (ParaAthleteClassification $classification) {
            if ($classification->athlete) {
                $classification->athlete->syncSportclassFromActiveClassification();
            }
        });

        static::deleted(function (ParaAthleteClassification $classification) {
            if ($classification->athlete) {
                $classification->athlete->syncSportclassFromActiveClassification();
            }
        });
    }

    public function classifiers()
    {
        return $this->belongsToMany(
            ParaClassifier::class,
            'para_athlete_classification_classifier',
            'para_athlete_classification_id',
            'para_classifier_id'
        )->withPivot('role')->withTimestamps();
    }

    public function techClassifiers()
    {
        return $this->classifiers()->wherePivotIn('role', ['TECH1', 'TECH2']);
    }

    public function medClassifier()
    {
        return $this->classifiers()->wherePivot('role', 'MED');
    }

}
