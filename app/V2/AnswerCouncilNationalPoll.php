<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class AnswerCouncilNationalPoll extends Model
{
    protected $fillable = ['answer','question_id'];
    protected $hidden = ['created_at','updated_at','question_id'];
}
