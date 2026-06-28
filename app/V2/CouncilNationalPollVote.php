<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class CouncilNationalPollVote extends Model
{
    protected $fillable = ['poll_id','question_id','answer_id','user_id','weight'];

    public function user(){
        return $this->belongsTo(AppUser::class,'user_id');
    }

    public function question(){
        return $this->belongsTo(CouncilNationalPollQuestion::class,'question_id');
    }

    public function answer(){
        return $this->belongsTo(AnswerCouncilNationalPoll::class,'answer_id');
    }
}
