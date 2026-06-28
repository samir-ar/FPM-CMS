<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Http\Traits\MyTranslationTrait;

class Biography extends Model
{
    use SoftDeletes;
    use MyTranslationTrait;

    public $translatable = ['title','body'];

    protected $fillable=[
        'title',
        'body',
    ];

    public function persons(){
        return $this->belongsTo(Person::class,'person_id');
    }

}
