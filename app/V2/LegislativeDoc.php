<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class LegislativeDoc extends Model
{
    protected $table = 'legislative_docs';
    protected $fillable = ['tab', 'title', 'file_name', 'order'];

    public static array $tabs = [
        'sawdir'     => 'القوانين الصادرة من التيار',
        'iqtirahaat' => 'اقتراحات القوانين المقدمة',
    ];
}
