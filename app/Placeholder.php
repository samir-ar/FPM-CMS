<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Storage;

class Placeholder extends Model
{
    // public function getImageAttribute($attr)
    // {
    //     if(!$attr)
    //         return null;

    //     // return 'images/placeholders/'.rawurlencode($attr);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/placeholders/' . $attr);

    // }
}
