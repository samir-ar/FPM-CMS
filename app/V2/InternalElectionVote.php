<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class InternalElectionVote extends Model
{
    public $timestamps =false;

       
    public function internalElection(){
        return $this->belongsTo(InternalElection::class);
    }

    public function candidate(){
        return $this->belongsTo(InternalElectionCandidate::class);
    }

    public function user(){
        return $this->belongsTo(AppUser::class);
    }
}
