<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Storage;

class InternalElectionCandidate extends Model
{
    public $timestamps = false;

    // Yajra DataTables serializes each row to an array before applying
    // ->addColumn() overrides — without this, computed-only attributes
    // like `name` are missing from that array and the table breaks.
    protected $appends = ['name', 'photo_url'];
    // public function getImageNameAttribute($attr){
    //     if(!$attr) return null;
    //     // return "/images/candidates"."/".$attr;
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/candidates/' . $attr);

    // }


    // Kept as `name` so every existing read (sorting, the duplicate check,
    // the admin list, the results report) keeps working unchanged even
    // though the name is now stored as 3 separate columns.
    public function getNameAttribute(){
        return trim($this->first_name . ' ' . $this->father_name . ' ' . $this->family_name);
    }

    // fpm-linked candidates (member_id set) have no local image_name —
    // their photo is served live from the same endpoint the app already
    // uses for representatives' photos.
    public function getPhotoUrlAttribute(){
        if ($this->member_id) {
            return url('api/v2/member-photo/' . $this->member_id);
        }

        return url('images/candidates/' . $this->image_name);
    }

    public function internalElection(){
        return $this->belongsTo(InternalElection::class,"election_id");
    }

    public function internalElectionVotes(){
        return $this->hasMany(InternalElectionVote::class,"candidate_id");
    }

    public function electionStates(){
        return $this->belongsToMany(ElectionState::class, 'internal_election_candidate_states', 'candidate_id', 'election_state_id');
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
