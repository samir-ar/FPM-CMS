<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class DirectoryMember extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['latitude' => 'float', 'longitude' => 'float'];

    public function businessType()
    {
        return $this->belongsTo(BusinessType::class, 'business_type_id');
    }
}
