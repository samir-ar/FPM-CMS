<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BillTransaction extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(AppUser::class, 'user_id', 'id');
    }
}
