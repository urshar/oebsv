<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParaAthlete extends Model
{
    protected $table = 'para_athletes';

    protected $guarded = [];

    protected $casts = [
        'birthdate' => 'date',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(ParaClub::class, 'para_club_id');
    }

    public function nation(): BelongsTo
    {
        return $this->belongsTo(Nation::class, 'nation_id');
    }

    public function subregion(): BelongsTo
    {
        return $this->belongsTo(Subregion::class, 'subregion_id');
    }

    public function entries(): ParaAthlete|HasMany
    {
        return $this->hasMany(ParaEntry::class, 'para_athlete_id');
    }

    public function classificationForEvent(?ParaEvent $event): ?string
    {
        // If we have no event or no swimstyle, fall back to "best guess"
        if (!$event || !$event->swimstyle) {
            return $this->sportclass_s
                ?? $this->sportclass_sb
                ?? $this->sportclass_sm;
        }

        $stroke = strtoupper($event->swimstyle->stroke ?? '');

        // Map stroke → correct class type
        // adjust stroke strings if you use German (e.g. BRUST, LAGEN)
        if (in_array($stroke, ['BREAST', 'BRUST'], true)) {
            return $this->sportclass_sb ?? $this->sportclass_s;
        }

        if (in_array($stroke, ['MEDLEY', 'LAGEN', 'IM'], true)) {
            return $this->sportclass_sm ?? $this->sportclass_s;
        }

        // Default: S-Class (FREE, BACK, FLY, etc.)
        return $this->sportclass_s
            ?? $this->sportclass_sb
            ?? $this->sportclass_sm;
    }

    public function classifications(): HasMany
    {
        return $this->hasMany(ParaAthleteClassification::class, 'para_athlete_id')
            ->orderByDesc('classification_date')
            ->orderByDesc('id');
    }

    /**
     * Komfort: aktive / letzte Klassifikation.
     * (Jüngste nach Datum; falls es keine gibt, null)
     */
    public function activeClassification(): ?ParaAthleteClassification
    {
        return $this->classifications()
            ->orderByDesc('classification_date')
            ->first();
    }

    /**
     * Synchronisiert die sportclass_* Felder des Athleten
     * mit der aktuell aktiven Klassifikation.
     *
     * - Wenn es eine Klassifikation gibt: Kopieren.
     * - Wenn keine Klassifikation mehr existiert: Felder leeren.
     */
    public function syncSportclassFromActiveClassification(): void
    {
        $active = $this->activeClassification();

        $dirty = false;
        if ($active) {

            if ($this->sportclass_s !== $active->sportclass_s) {
                $this->sportclass_s = $active->sportclass_s;
                $dirty = true;
            }
            if ($this->sportclass_sb !== $active->sportclass_sb) {
                $this->sportclass_sb = $active->sportclass_sb;
                $dirty = true;
            }
            if ($this->sportclass_sm !== $active->sportclass_sm) {
                $this->sportclass_sm = $active->sportclass_sm;
                $dirty = true;
            }
            if ($this->sportclass_exception !== $active->sportclass_exception) {
                $this->sportclass_exception = $active->sportclass_exception;
                $dirty = true;
            }

        } else {
            // Keine Klassifikation mehr → Felder optional leeren

            foreach (['sportclass_s', 'sportclass_sb', 'sportclass_sm', 'sportclass_exception'] as $field) {
                if ($this->{$field} !== null) {
                    $this->{$field} = null;
                    $dirty = true;
                }
            }

        }
        if ($dirty) {
            $this->save();
        }
    }


    // Später: Meldungen / Ergebnisse-Relationen (entries, results) kannst du hier anhängen.
}
