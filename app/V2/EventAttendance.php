<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class EventAttendance extends Model
{
    protected $table = 'event_attendance';

    protected $fillable = ['event_id', 'member_id', 'checked_in_by_app_user_id', 'added_by_admin_id', 'checked_in_at'];

    public function event()
    {
        return $this->belongsTo(CheckinEvent::class, 'event_id');
    }

    public function scannedBy()
    {
        return $this->belongsTo(AppUser::class, 'checked_in_by_app_user_id');
    }

    public function addedByAdmin()
    {
        return $this->belongsTo(\App\User::class, 'added_by_admin_id');
    }
}
