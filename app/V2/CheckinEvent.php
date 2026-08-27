<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class CheckinEvent extends Model
{
    protected $table = 'checkin_events';

    protected $fillable = ['name', 'location', 'event_date', 'is_active'];

    protected $casts = [
        'event_date' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function attendance()
    {
        return $this->hasMany(EventAttendance::class, 'event_id');
    }
}
