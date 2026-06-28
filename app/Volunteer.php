<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\MyTranslationTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storage;

class Volunteer extends Model
{
    use MyTranslationTrait;
    use SoftDeletes;

    public $translatable = ['title', 'text'];

    // public function getImageAttribute($attr)
    // {
    //     if(!$attr)
    //         return null;

    //     // return 'images/volunteers/'.rawurlencode($attr);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/volunteers/' . $attr);

    // }

    public function users()
    {
        return $this->belongsToMany(AppUser::class, 'users_volunteers', 'volunteer_id', 'user_id')
            ->withTimestamps();
    }

}
