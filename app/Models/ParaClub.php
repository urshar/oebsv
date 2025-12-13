<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
