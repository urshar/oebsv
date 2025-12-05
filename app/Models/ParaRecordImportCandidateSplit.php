<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParaRecordImportCandidateSplit extends Model
{
    protected $table = 'para_record_import_candidate_splits';

    protected $guarded = [];

    protected $casts = [
        'swimtime_ms' => 'integer',
        'distance'    => 'integer',
        'order'       => 'integer',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ParaRecordImportCandidate::class, 'para_record_import_candidate_id');
    }
}
