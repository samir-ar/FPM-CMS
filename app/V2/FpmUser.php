<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class FpmUser extends Model
{
    protected $primaryKey = 'MemberId'; // Set the primary key to 'MemberId'

    public function fpmUsersActions()
    {
        return $this->hasMany(FpmUsersAction::class, 'MemberId', 'MemberId');
    }
}
