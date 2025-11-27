<?php

namespace App\Services;

use App\Models\ParaAthlete;
use App\Models\ParaEvent;
use App\Models\ParaEventAgegroup;
use Illuminate\Support\Carbon;

class AgegroupResolver
{
    /**
     * Findet eine passende Agegroup für Athlet+Event
     * anhand Alter (zum ageDate) und Geschlecht.
     */
    public function resolve(ParaEvent $event, ParaAthlete $athlete, Carbon $ageDate): ?ParaEventAgegroup
    {
        $agegroups = $event->agegroups;

        if ($agegroups->isEmpty() || !$athlete->birthdate) {
            return null;
        }

        $birth  = Carbon::parse($athlete->birthdate);
        $age    = $birth->diffInYears($ageDate);
        $gender = $athlete->gender;

        foreach ($agegroups as $ag) {
            $minOk = is_null($ag->age_min) || $age >= $ag->age_min;
            $maxOk = is_null($ag->age_max) || $age <= $ag->age_max;

            $genderOk =
                !$ag->gender ||
                $ag->gender === 'X' ||
                $ag->gender === $gender;

            if ($minOk && $maxOk && $genderOk) {
                return $ag;
            }
        }

        return null;
    }
}
