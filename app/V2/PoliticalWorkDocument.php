<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class PoliticalWorkDocument extends Model
{
    protected $fillable = ['category', 'title', 'file_name', 'order'];

    public static array $categories = [
        'takattol'       => 'تكتل لبنان القوي',
        'haya_siyasiya'  => 'الهيئة السياسية',
        'majlis_siyasi'  => 'المجلس السياسي',
    ];
}
