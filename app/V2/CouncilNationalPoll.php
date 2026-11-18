<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CouncilNationalPoll extends Model
{
    // "Delete" only marks the poll as deleted (deleted_at); the poll and all its votes stay in the database.
    use SoftDeletes;

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

    public function meeting(){
        return $this->belongsTo(CouncilMeeting::class,'meeting_id');
    }

    public function groups(){
        return $this->belongsToMany(Group::class,'council_national_poll_groups');
    }



}
