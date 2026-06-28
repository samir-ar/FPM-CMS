<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded=['id'];

    protected $casts = [
        'bool_flag' => 'boolean',
        'force_update_ios' => 'boolean',
        'force_update_android' => 'boolean'
     ];
}