<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class FpmUsersAction extends Model
{
    public function fpmUser()
    {
        return $this->belongsTo(FpmUser::class, 'MemberId', 'MemberId');
    }
}
