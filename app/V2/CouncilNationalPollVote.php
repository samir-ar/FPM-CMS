<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class CouncilNationalPollVote extends Model
{
    protected $fillable = ['poll_id','question_id','answer_id','user_id','member_id','weight','device_id','device_model','hardware_id','voted_at'];

    public function user(){
        // withTrashed: a vote cast from an account that was later hidden by the duplicate clean-up must still show its voter
        return $this->belongsTo(AppUser::class,'user_id')->withTrashed();
    }

    public function question(){
        return $this->belongsTo(CouncilNationalPollQuestion::class,'question_id');
    }

    public function answer(){
        return $this->belongsTo(AnswerCouncilNationalPoll::class,'answer_id');
    }
}
