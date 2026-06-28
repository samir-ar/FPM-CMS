<?php

namespace App\V2;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class LocalBody extends Model
{
    public function scopeFilter($query)
    {
        $district = Input::get('district');
        
        return $query->where('district', '=', $district);
        if(!$district)return $query;
        
    }

    public function candidate()
    {
        return $this->belongsTo(AppUser::class,"candidate_id");
    }    

    public function registerer()
    {
        return $this->belongsTo(AppUser::class,"registerer_id");
    }
}
