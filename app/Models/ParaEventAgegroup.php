<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParaEventAgegroup extends Model
{
    use HasFactory;

    protected $table = 'para_event_agegroups';

    protected $guarded = [];

    public function event()
    {
        return $this->belongsTo(ParaEvent::class, 'para_event_id');
    }
}
