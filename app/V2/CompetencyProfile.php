<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class CompetencyProfile extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(AppUser::class, 'user_id');
    }
}
