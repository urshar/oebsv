<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nation extends Model
{
    use HasFactory;

    protected $table = 'nations';

    protected $guarded = [];

    public function continent(): BelongsTo
    {
        return $this->belongsTo(Continent::class);
    }

    public function subregions(): HasMany|Nation
    {
        return $this->hasMany(Subregion::class);
    }

    public function paraClubs(): HasMany|Nation
    {
        return $this->hasMany(ParaClub::class, 'nation_id');
    }

    public function paraAthletes(): HasMany|Nation
    {
        return $this->hasMany(ParaAthlete::class, 'nation_id');
    }

    public function paraMeets(): HasMany|Nation
    {
        return $this->hasMany(ParaMeet::class, 'nation_id');
    }
}
