<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class InternalElectionVoterViewer extends Model
{
    protected $fillable = ['election_id', 'member_id', 'election_state_id'];

    public function internalElection()
    {
        return $this->belongsTo(InternalElection::class, 'election_id');
    }

    public function electionState()
    {
        return $this->belongsTo(ElectionState::class, 'election_state_id');
    }
}
