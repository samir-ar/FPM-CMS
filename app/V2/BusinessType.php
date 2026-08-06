<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\MyTranslationTrait;

class BusinessType extends Model
{
    use MyTranslationTrait;

    protected $guarded = ['id'];
    public $translatable = ['name'];

    public function posts()
    {
        return $this->hasMany(CommunityPost::class, 'business_type_id');
    }
}
