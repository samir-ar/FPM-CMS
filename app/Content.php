<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Storage;

class Content extends Model
{
    protected $table='contents';

    // public function getImageAttribute($val)
    // {
    //     if(!$val)
    //         return null;

    //     // return 'images/content/'.$val;
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/content/' . $val);
    // }
}
