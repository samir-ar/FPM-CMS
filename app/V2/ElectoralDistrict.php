<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class ElectoralDistrict extends Model
{
    protected $guarded = ['id'];

    public function towns()
    {
        return $this->hasMany(Town::class, 'electoral_district_id');
    }
}
