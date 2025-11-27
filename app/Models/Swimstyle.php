<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Swimstyle extends Model
{
    use HasFactory;

    protected $table = 'swimstyles';

    protected $guarded = [];

    public function events()
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
