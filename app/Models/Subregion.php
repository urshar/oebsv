<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subregion extends Model
{
    use HasFactory;

    protected $table = 'subregions';

    protected $guarded = [];

    public function nation()
    {
        return $this->belongsTo(Nation::class);
    }

    public function paraClubs()
    {
        return $this->hasMany(ParaClub::class, 'subregion_id');
    }

    public function paraAthletes()
    {
        return $this->hasMany(ParaAthlete::class, 'subregion_id');
    }
}
