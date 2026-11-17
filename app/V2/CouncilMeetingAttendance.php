<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class CouncilMeetingAttendance extends Model
{
    protected $table = 'council_meeting_attendance';

    protected $fillable = ['meeting_id', 'member_id', 'weight', 'checked_in_at', 'added_by_admin_id', 'scanned_by_app_user_id'];

    protected $casts = ['checked_in_at' => 'datetime'];

    public function meeting()
    {
        return $this->belongsTo(CouncilMeeting::class, 'meeting_id');
    }
}
