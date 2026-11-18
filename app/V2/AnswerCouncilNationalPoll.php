<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class AnswerCouncilNationalPoll extends Model
{
    protected $fillable = ['answer','question_id','member_id'];
    protected $hidden = ['created_at','updated_at','question_id','member_id'];
    protected $appends = ['photo'];

    // A candidate picked from the member list has a photo; a hand-typed option has none (null).
    public function getPhotoAttribute()
    {
        return $this->member_id ? url('/api/v2/member-photo/' . $this->member_id) : null;
    }
}
