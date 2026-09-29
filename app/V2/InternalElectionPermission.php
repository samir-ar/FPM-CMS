<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class InternalElectionPermission extends Model
{
    public $timestamps = true;

    protected $fillable = ['election_id', 'member_id'];

    public function election()
    {
        return $this->belongsTo(InternalElection::class, 'election_id');
    }
}
