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
        // kleiner Helper für das Frontend
        if ($this->nameDe) {
            return $this->nameDe;
        }

        return sprintf('%dm %s%s',
            $this->distance,
            $this->stroke,
            $this->is_relay ? ' Staffel' : ''
        );
    }

    public function getLabelEnAttribute(): string
    {
        if ($this->nameEn) {
            return $this->nameEn;
        }

        return sprintf('%dm %s%s',
            $this->distance,
            $this->stroke,
            $this->is_relay ? ' Relay' : ''
        );
    }
}
