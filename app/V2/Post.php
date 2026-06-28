<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    public $timestamps = false;
       

    public function centralCommittee(){
        return $this->hasMany(CentralCommittee::class);
    }


    public function consultingCommittee(){
        return $this->hasMany(ConsultingCommittee::class);
    }

}