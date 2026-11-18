<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CouncilNationalPoll extends Model
{
    // "Delete" only marks the poll as deleted (deleted_at); the poll and all its votes stay in the database.
    use SoftDeletes;

    // The moment voting ends (null = no closing time). After it the poll is shown as closed and cannot be voted in.
    public function isClosed(): bool
    {
        return $this->closes_at && \Carbon\Carbon::parse($this->closes_at)->isPast();
    }

    public function closesAtIso(): ?string
    {
        return $this->closes_at ? \Carbon\Carbon::parse($this->closes_at)->toIso8601String() : null;
    }

    public function format(){
        return [
            'id' => $this->id,
            'title'=> $this->title,
            'closes_at' => $this->closesAtIso(),
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
