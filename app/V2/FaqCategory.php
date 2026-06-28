<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\MyTranslationTrait;


class FaqCategory extends Model
{
    use MyTranslationTrait;

    protected $table = 'faqs_categories';

    public $translatable = ['name'];


    public function faqs()
    {
        return $this->hasMany(Faq::class, 'cat_id', 'id');
    }
}
