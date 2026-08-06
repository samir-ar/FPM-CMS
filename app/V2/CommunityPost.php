<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class CommunityPost extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['approved_at' => 'datetime'];

    public function businessType()
    {
        return $this->belongsTo(BusinessType::class, 'business_type_id');
    }

    public function user()
    {
        return $this->belongsTo(AppUser::class, 'user_id');
    }
}
