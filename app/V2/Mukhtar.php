<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class Mukhtar extends Model
{
    protected $fillable = [
        'qada',
        'village_name',
        'neighborhood',
        'full_name',
        'position',
        'votes',
        'phone',
        'is_mountasib',
        'sort_order',
    ];

    protected $casts = [
        'is_mountasib' => 'boolean',
    ];
}
