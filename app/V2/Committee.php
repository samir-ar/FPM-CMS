<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class Committee extends Model
{
    public $timestamps = false;
    public function centralCommitteeCoordinator()
    {
        return $this->hasMany(CentralCommitteeCoordinator::class);
    }
    
    public function centralCommittees()
    {
        return $this->hasMany(CentralCommittee::class);
    }


    public function consultingCommittee()
    {
        return $this->hasMany(ConsultingCommittee::class);
    }
}
