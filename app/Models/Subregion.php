<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $id
 * @property string|null $lsvCode
 * @property string $nameDe
 * @property string|null $nameEn
 */
class Subregion extends Model
{
    protected $table = 'subregions';

    protected $guarded = [];

    public function nation(): BelongsTo
    {
        return $this->belongsTo(Nation::class);
    }

    public function paraClubs(): HasMany
    {
        return $this->hasMany(ParaClub::class, 'subregion_id');
    }

    public function paraAthletes(): HasMany
    {
        return $this->hasMany(ParaAthlete::class, 'subregion_id');
    }
}
