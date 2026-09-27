<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\MyTranslationTrait;

class LegislativeDocSubcategory extends Model
{
    use MyTranslationTrait;

    protected $table = 'legislative_doc_subcategories';

    public $translatable = ['name'];

    public function category()
    {
        return $this->belongsTo(LegislativeDocCategory::class, 'category_id', 'id');
    }

    public function docs()
    {
        return $this->hasMany(LegislativeDoc::class, 'subcategory_id', 'id');
    }
}
