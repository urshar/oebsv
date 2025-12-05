<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParaRecordSplit extends Model
{
    protected $fillable = [
        'para_record_id',
        'distance',
        'order',
        'swimtime_ms',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(ParaRecord::class, 'para_record_id');
    }
}
