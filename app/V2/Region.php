<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = ["name","district_id"];

    public function district(){
        return $this->belongsTo(District::class);
    }
}
