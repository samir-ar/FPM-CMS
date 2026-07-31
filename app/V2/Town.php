<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class Town extends Model
{
    protected $guarded = ['id'];

    public function electoralDistrict()
    {
        return $this->belongsTo(ElectoralDistrict::class, 'electoral_district_id');
    }
}
