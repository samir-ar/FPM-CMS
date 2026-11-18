<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InternalElection extends Model
{
    // "Delete" only marks the election as deleted (deleted_at); the election and all its votes stay in the database.
    use SoftDeletes;

    public function votes(){
        return $this->hasMany(InternalElectionVote::class);
    }

    public function candidates(){
        return $this->hasMany(InternalElectionCandidate::class,"election_id");
    }
    
}
