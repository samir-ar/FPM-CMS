<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class CouncilNationalPollPermission extends Model
{

    protected $fillable=['poll_id','user_id','vote_weight','member_id'];

    public function poll(){
        return $this->belongsTo(CouncilNationalPoll::class,'poll_id');
    }

    //TODO This should be removed since
    public function user(){
        return $this->belongsTo(AppUser::class,'user_id');
    }
}
