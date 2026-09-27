<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\MyTranslationTrait;

class LegislativeDocCategory extends Model
{
    use MyTranslationTrait;

    protected $table = 'legislative_doc_categories';

    public $translatable = ['name'];

    public function subcategories()
    {
        return $this->hasMany(LegislativeDocSubcategory::class, 'category_id', 'id');
    }

    public function docs()
    {
        return $this->hasMany(LegislativeDoc::class, 'category_id', 'id');
    }
}
