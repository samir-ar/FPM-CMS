<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\MyTranslationTrait;
use Storage;

class Album extends Model
{
    use MyTranslationTrait;

    protected $translatable = ['name', 'description'];
    protected $fillable = ["name","type","description","thumbnail"];

    public function medias(){
        return $this->hasMany(Media::class);
    }


    // public function getImageAttribute($attr)
    // {
    //     // return rawurlencode($attr);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'media/' . $attr);

    // }

    // public function getThumbnailAttribute($attr)
    // {
    //     // return rawurlencode($attr);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'media/thumbnail/' . $attr);

    // }

}
