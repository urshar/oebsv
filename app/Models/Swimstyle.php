<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Swimstyle extends Model
{
    protected $table = 'swimstyles';

    protected $guarded = [];

    protected $casts = [
        'is_relay' => 'boolean',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(ParaEvent::class, 'swimstyle_id');
    }

    public function getLabelDeAttribute(): string
    {
        if ($this->stroke_name_de) {
            return $this->stroke_name_de;
        }

        return sprintf('%dm %s%s',
            $this->distance,
            $this->stroke,
            $this->is_relay ? ' Staffel' : ''
        );
    }

    public function getLabelEnAttribute(): string
    {
        if ($this->stroke_name_en) {
            return $this->stroke_name_en;
        }

        return sprintf('%dm %s%s',
            $this->distance,
            $this->stroke,
            $this->is_relay ? ' Relay' : ''
        );
    }
}
