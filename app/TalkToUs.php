<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TalkToUs extends Model
{
    protected $table='talk_to_us';

    public function user()
    {
        return $this->belongsTo(AppUser::class, 'user_id', 'id');
    }
}
