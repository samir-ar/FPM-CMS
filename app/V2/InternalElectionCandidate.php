<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Storage;

class InternalElectionCandidate extends Model
{
    public $timestamps = false;
    // public function getImageNameAttribute($attr){
    //     if(!$attr) return null;
    //     // return "/images/candidates"."/".$attr;
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/candidates/' . $attr);

    // }


    public function internalElection(){
        return $this->belongsTo(InternalElection::class,"election_id");
    }

    public function internalElectionVotes(){
        return $this->hasMany(InternalElectionVote::class,"candidate_id");
    }

    public function electionState(){
        return $this->belongsTo(ElectionState::class);
    }

    public function scopeOrderByCommentRank($query, $order = 'desc')
    {
        return $query->leftJoin('comment_votes', 'comment_votes.comment_id', '=', 'comments.id')
            ->groupBy('comments.id')
            ->addSelect(['*', \DB::raw('sum(position) as commentRank')])
            ->orderBy('commentRank', $order);
    }

    public function scopeRank($q){
        return $q->leftJoin('internal_election_votes','internal_election_candidates.id','=','internal_election_votes.candidate_id')->select()->addSelect([\DB::raw('sum(`rank`) as `_rank`')])->groupBy('internal_election_candidates.id');
        //return $q->select()->addSelect(\DB::raw('sum( select `rank` from internal_election_votes where `candidate_id` = '.$q->id.') as `_rank`') )->groupBy('id');
    }

}
