<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\MyTranslationTrait;
use Storage;

class Representative extends Model
{
    use MyTranslationTrait;

    protected $table='representatives';

    public $translatable = ['name', 'category'];


    // public function getImageAttribute($val)
    // {
    //     if(!$val)
    //         return null;

    //     return 'images/representatives/'.rawurlencode($val);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/representatives/' . $val);

    // }


}
