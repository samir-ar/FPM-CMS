<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class MunicipalityMember extends Model
{
    protected $fillable = [
        'qada',
        'municipality_name',
        'village_name',
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
