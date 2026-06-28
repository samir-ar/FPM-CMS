<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    protected $fillable = ['name'];

    public function regions()
    {
        return $this->hasMany(Region::class);
    }
}
