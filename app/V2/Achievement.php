<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Http\Traits\MyTranslationTrait;



class Achievement extends Model
{
    use SoftDeletes;
    use MyTranslationTrait;

    public $translatable = ['title','text'];


    protected $fillable = [
        'title',
        'text',
    ];


}
