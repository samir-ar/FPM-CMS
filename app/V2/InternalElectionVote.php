<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Storage;

class InternalElectionVote extends Model
{
    public $timestamps =false;

    public function getSelfieUrlAttribute(){
        if (!$this->selfie_path) {
            return null;
        }

        return Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/' . 'storage/' . 'images/internal_election_selfies/' . $this->selfie_path);
    }

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
