<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class CentralCommittee extends Model
{
    public function scopeFilter($query)
    {
        $district = Input::get('district');
        
        return $query->where('district', '=', $district);
        if(!$district)return $query;
    }

    public function candidate()
    {
        return $this->belongsTo(AppUser::class,'candidate_id');
    }    

    public function registerer()
    {
        return $this->belongsTo(AppUser::class,'registerer_id');
    }    

    public function committee()
    {
        return $this->belongsTo(Committee::class);
    }    

    public function post()
    {
        return $this->belongsTo(Post::class);
    }    

    
    
}
