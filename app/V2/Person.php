<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\MyTranslationTrait;
use Storage;

class Person extends Model
{
    use MyTranslationTrait;

    protected $table = 'persons';
    public $translatable = ['name', 'category'];
    protected $fillable = ['name','image','dynamic_representative_id','order','type','rep_order'];

    public function dynamicRepresentative(){
        return  $this->belongsTo(DynamicRepresentative::class);
    }

    // public function getImageAttribute($val)
    // {
    //     if(!$val)
    //         return null;

    //     // return 'images/representatives/'.rawurlencode($val);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/representatives/' . $val);

    // }

    public function position(){
        return $this->belongsTo(RepresentativePosition::class,'representative_position_id');
    }

    public $with = ['position'];
}
