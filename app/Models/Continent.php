<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Continent extends Model
{
    use HasFactory;

    protected $table = 'continents';

    protected $guarded = [];

    public function nations(): Continent|HasMany
    {
        return $this->hasMany(Nation::class);
    }
}
