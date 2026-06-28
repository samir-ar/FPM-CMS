<?php

namespace App\V2;

use App\Acme\Helpers\Datatables;
use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    protected $table='user_favorites';

    public function user()
    {
        return $this->hasOne(AppUser::class, 'user_id', 'id');
    }
}
