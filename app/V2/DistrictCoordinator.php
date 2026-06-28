<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class DistrictCoordinator extends Model
{

    protected $table = 'district_coordinators';
  // protected $dates = ['phase_1','phase_2','phase_3','phase_4','phase_5','phase_6','phase_7'];
   // protected $dateFormat = 'd-m-Y';

    protected $fillable = ['state','candidate_id','district','phase_1','phase_2','phase_3','phase_4','phase_5','phase_6','phase_7','popularization_no'];
    
    public function candidate()
    {
        return $this->belongsTo(AppUser::class,'candidate_id');
    }
    
    public function Registerer()
    {
        return $this->belongsTo(AppUser::class,'registerer_id');
    }
    
}
