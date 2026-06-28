<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Storage;
class DonationImage extends Model
{
    protected $table = "donation_image";


    // public function getImageAttribute($attr)
    // {
    //     if(!$attr)
    //         return null;

    //     // return 'images/donations/'.$attr;
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/donations/' . $attr);

    // }

}
