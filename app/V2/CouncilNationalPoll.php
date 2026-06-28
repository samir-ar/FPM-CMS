<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class CouncilNationalPoll extends Model
{

    public function format(){
        return [
            'id' => $this->id,
            'title'=> $this->title,
            'questions' => $this->questions
            ];
    }

    public function questions(){
        return $this->hasMany(CouncilNationalPollQuestion::class,'poll_id');
    }

    //Voters
    public function users(){
        return $this->belongsToMany(AppUser::class,'council_national_poll_votes','user_id','poll_id');
    }

    public function groups(){
        return $this->belongsToMany(Group::class,'council_national_poll_groups');
    }



}
