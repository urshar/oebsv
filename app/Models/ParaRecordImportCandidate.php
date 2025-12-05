<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParaRecordImportCandidate extends Model
{
    protected $table = 'para_record_import_candidates';

    protected $guarded = [];

    protected $casts = [
        'recordlist_updated_at' => 'date',
        'swum_at'               => 'date',
        'athlete_birthdate'     => 'date',
        'resolved_at'           => 'datetime',
        'is_relay'              => 'bool',
        'missing_athlete'       => 'bool',
        'missing_club'          => 'bool',
    ];

    public function nation(): BelongsTo
    {
        return $this->belongsTo(Nation::class, 'nation_id');
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(ParaAthlete::class, 'para_athlete_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(ParaClub::class, 'para_club_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ParaRecord::class, 'para_record_id');
    }

    public function splits(): HasMany
    {
        return $this->hasMany(ParaRecordImportCandidateSplit::class, 'para_record_import_candidate_id');
    }
}
