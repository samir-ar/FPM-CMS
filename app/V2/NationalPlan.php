<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class NationalPlan extends Model
{
    protected $table = 'national_plans';
    protected $fillable = ['tab', 'title', 'file_name', 'order'];

    public static array $tabs = [
        'iqtisad'    => 'الإقتصادي',
        'kahraba'    => 'الكهرباء',
        'maa'        => 'الماء',
        'muhajareen' => 'المهجرين',
        'lamarkaziya'=> 'اللامركزية الإدارية',
    ];
}
