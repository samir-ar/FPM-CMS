<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class ConsultingCommittee extends Model
{
    public function candidate(){
        return $this->belongsTo(AppUser::class,"candidate_id");
    }

    public function post(){
        return $this->belongsTo(Post::class);
    }

    public function committee(){
        return $this->belongsTo(Committee::class);
    }
    

}
