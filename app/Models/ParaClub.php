<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParaClub extends Model
{
    use HasFactory;

    protected $table = 'para_clubs';

    protected $guarded = [];

    public function nation()
    {
        return $this->belongsTo(Nation::class, 'nation_id');
    }

    public function subregion()
    {
        return $this->belongsTo(Subregion::class, 'subregion_id');
    }

    public function paraAthletes()
    {
        return $this->hasMany(ParaAthlete::class, 'para_club_id');
    }

    // Falls du später eine Verbindung zu ParaMeet herstellst (z.B. club_meet Pivot),
    // kannst du hier ein belongsToMany ergänzen.
}
