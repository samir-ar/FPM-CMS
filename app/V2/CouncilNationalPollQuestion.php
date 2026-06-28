<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class CouncilNationalPollQuestion extends Model
{
    protected $fillable = ['question','poll_id'];
    protected $with = ['answers'];
    protected $hidden = ['created_at','updated_at','poll_id'];

    public function councilNationalPoll(){
        return $this->belongsTo(CouncilNationalPoll::class,'poll_id');
    }
    //Answers
    public function answers(){
        return $this->hasMany(AnswerCouncilNationalPoll::class,'question_id');
    }
}
