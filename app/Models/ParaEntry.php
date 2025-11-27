<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParaEntry extends Model
{
    use HasFactory;

    protected $table = 'para_entries';

    protected $guarded = [];

    protected $casts = [
        'qualifying_date' => 'date',
    ];

    public function meet()
    {
        return $this->belongsTo(ParaMeet::class, 'para_meet_id');
    }

    public function session()
    {
        return $this->belongsTo(ParaSession::class, 'para_session_id');
    }

    public function event()
    {
        return $this->belongsTo(ParaEvent::class, 'para_event_id');
    }

    public function agegroup()
    {
        return $this->belongsTo(ParaEventAgegroup::class, 'para_event_agegroup_id');
    }

    public function athlete()
    {
        return $this->belongsTo(ParaAthlete::class, 'para_athlete_id');
    }

    public function club()
    {
        return $this->belongsTo(ParaClub::class, 'para_club_id');
    }
}
