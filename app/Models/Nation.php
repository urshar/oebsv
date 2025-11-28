<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nation extends Model
{
    protected $table = 'nations';

    protected $fillable = [
        'nameEn',
        'nameDe',
        'worldAquaNF',
        'worldAquaNFurl',
        'worldParaNF',
        'worldParaNFurl',
        'continent_id',
        'ioc',
        'iso2',
        'iso3',
        'officialNameEn',
        'officialShortEn',
        'officialNameDe',
        'officialShortDe',
        'officialNameCn',
        'officialShortCn',
        'officialNameFr',
        'officialShortFr',
        'officialNameAr',
        'officialShortAr',
        'officialNameRu',
        'officialShortRu',
        'officialNameEs',
        'officialShortEs',
        'subRegionName',
        'tld',
        'currencyAlphabeticCode',
        'currencyName',
        'isIndependent',
        'Capital',
        'IntermediateRegionName',
    ];

    public function getDisplayNameAttribute(): string
    {
        $name = $this->nameDe
            ?: $this->nameEn
            ?: $this->officialShortEn
            ?: $this->officialNameEn;

        // **IOC zuerst**, nur Fallback, falls mal keins gesetzt ist
        $code = $this->ioc ?: $this->iso3 ?: $this->iso2;

        return trim(($code ? $code . ' – ' : '') . ($name ?: ''));
    }

    public function continent(): BelongsTo
    {
        return $this->belongsTo(Continent::class, 'continent_id');
    }

    public function subregions(): HasMany
    {
        return $this->hasMany(Subregion::class);
    }

    public function paraClubs(): HasMany
    {
        return $this->hasMany(ParaClub::class, 'nation_id');
    }

    public function paraAthletes(): HasMany
    {
        return $this->hasMany(ParaAthlete::class, 'nation_id');
    }

    public function paraMeets(): HasMany
    {
        return $this->hasMany(ParaMeet::class, 'nation_id');
    }
}
