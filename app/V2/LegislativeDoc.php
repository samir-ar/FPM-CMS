<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class LegislativeDoc extends Model
{
    protected $table = 'legislative_docs';
    protected $fillable = ['category_id', 'subcategory_id', 'title', 'date', 'file_name', 'order'];

    public function category()
    {
        return $this->belongsTo(LegislativeDocCategory::class, 'category_id', 'id');
    }

    public function subcategory()
    {
        return $this->belongsTo(LegislativeDocSubcategory::class, 'subcategory_id', 'id');
    }
}
