<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParaSplit extends Model
{
    protected $table = 'para_splits';

    protected $guarded = [];

    public function result(): BelongsTo
    {
        return $this->belongsTo(ParaResult::class, 'para_result_id');
    }

    public function getTimeFormattedAttribute(): string
    {
        return format_swim_time($this->time_ms);
    }

}
