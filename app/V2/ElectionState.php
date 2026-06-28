<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class ElectionState extends Model
{
    public $timestamps = false;

    public function internalElectionCandidates(){
        return $this->hasMany(InternalElectionCandidate::class);
    }
}
