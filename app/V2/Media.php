<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\MyTranslationTrait;
use Storage;

class Media extends Model
{
    use MyTranslationTrait;

    protected $translatable = ["name","description"];
    protected $fillable = ["name","description","slug","type","album_id","thumbnail","file_name"];

    public function album(){
        return $this->belongsTo(Album::class);
    }

    // public function getThumbnailAttribute($attr)
    // {
    //     // return rawurlencode($attr);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . MediaController$attr);

    // }

    // public function getImageAttribute($attr)
    // {
    //     // return rawurlencode($attr);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'media/' . $attr);

    // }
}

