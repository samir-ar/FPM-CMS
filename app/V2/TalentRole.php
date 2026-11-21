<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class TalentRole extends Model
{
    protected $table = 'talent_roles';
    protected $guarded = ['id'];
    protected $casts = ['allows_text' => 'boolean', 'is_active' => 'boolean'];
}
