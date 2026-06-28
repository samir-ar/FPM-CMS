<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class InternalElection extends Model
{
    public function votes(){
        return $this->hasMany(InternalElectionVote::class);
    }

    public function candidates(){
        return $this->hasMany(InternalElectionCandidate::class,"election_id");
    }
    
}
