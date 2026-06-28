<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\MyTranslationTrait;


class Faq extends Model
{
    use MyTranslationTrait;

    protected $table='faqs';

    public $translatable = ['name', 'details'];


    public function category()
    {
        return $this->belongsTo(FaqCategory::class, 'cat_id', 'id');
    }
}
