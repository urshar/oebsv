<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * @property int|null $id
 * @property string|null $nameDe
 * @property string|null $shortNameDe
 * @property string|null $region
 * @property int|null $subregion_id
 * @property Subregion|null $subregion
 */
class ParaClub extends Model
{
    protected $table = 'para_clubs';

    protected $guarded = [];

    public static function findByLenexOrName(?string $lenexClubId, ?string $clubName): ?self
    {
        $lenexClubId = trim((string) $lenexClubId);
        $clubName = trim((string) $clubName);

        $q = self::query();

        // Prefer LENEX Club-ID
        if ($lenexClubId !== '' && Schema::hasColumn('para_clubs', 'lenex_clubid')) {
            $club = (clone $q)->where('lenex_clubid', $lenexClubId)->first();
            if ($club) {
                return $club;
            }
        }

        if ($clubName === '') {
            return null;
        }

        return $q->where(function ($qq) use ($clubName) {
            $qq->where('nameDe', $clubName)
                ->orWhere('shortNameDe', $clubName)
                ->orWhere('nameEn', $clubName)
                ->orWhere('shortNameEn', $clubName);
        })->first();
    }

    public function nation(): BelongsTo
    {
        return $this->belongsTo(Nation::class, 'nation_id');
    }

    public function subregion(): BelongsTo
    {
        return $this->belongsTo(Subregion::class, 'subregion_id');
    }

    public function paraAthletes(): HasMany
    {
        return $this->hasMany(ParaAthlete::class, 'para_club_id');
    }

    public function relayEntries(): HasMany
    {
        return $this->hasMany(ParaRelayEntry::class, 'para_club_id');
    }

}
