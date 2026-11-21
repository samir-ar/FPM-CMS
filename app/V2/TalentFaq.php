<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class TalentFaq extends Model
{
    protected $table = 'talent_faqs';
    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean'];
}
