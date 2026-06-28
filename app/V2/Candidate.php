<?php
namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    protected $fillable = ['tracking_code','name','phone_number','email'];

    public function districtCoordinator()
    {
        return $this->hasMany(DistrictCoordinator::class);
    }    


    public function districtBody()
    {
        return $this->hasMany(DistrictBody::class);
    }    


    public function localBody()
    {
        return $this->hasMany(LocalBody::class);
    }    

  
    public function centralCommittee()
    {
        return $this->hasMany(CentralCommittee::class);
    }    

  
    public function centralCommitteeCoordinator()
    {
        return $this->hasMany(CentralCommitteeCoordinator::class);
    }    


    
    public function getNameTrackingCodeAttribute(){
        return $this->name.' '.$this->tracking_code;
    }
}
