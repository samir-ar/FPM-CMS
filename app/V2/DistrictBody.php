<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class DistrictBody extends Model
{
        
    public function candidate()
    {
        return $this->belongsTo(AppUser::class,"candidate_id");
    }   

    public function registerer()
    {
        return $this->belongsTo(AppUser::class,"registerer_id");
    }
}
