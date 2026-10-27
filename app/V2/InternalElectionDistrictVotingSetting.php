<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class InternalElectionDistrictVotingSetting extends Model
{
    protected $fillable = ['election_id', 'election_state_id', 'voting_enabled'];

    protected $casts = [
        'voting_enabled' => 'boolean',
    ];

    public function internalElection()
    {
        return $this->belongsTo(InternalElection::class, 'election_id');
    }

    public function electionState()
    {
        return $this->belongsTo(ElectionState::class, 'election_state_id');
    }
}
