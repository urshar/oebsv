<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParaEventAgegroup extends Model
{
    protected $table = 'para_event_agegroups';

    protected $guarded = [];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ParaEvent::class, 'para_event_id');
    }
}
