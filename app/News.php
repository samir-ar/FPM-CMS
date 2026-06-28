<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\MyTranslationTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storage;

class News extends Model
{

    use MyTranslationTrait;
    use SoftDeletes;

    public $translatable = ['title', 'details', 'source'];

    // public function getFileAttribute($attr)
    // {
    //     if(!$attr)
    //         return null;

    //     // return 'images/news/'.rawurlencode($attr);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'news/attachments/' . $attr);

    // }

    // public function getThumbnailAttribute($attr)
    // {
    //     if(!$attr)
    //         return null;

    //     // return 'images/news/'.rawurlencode($attr);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'news/videos/thumbnails/' . $attr);

    // }

    // public function getSourceImageAttribute($attr)
    // {
    //     if(!$attr)
    //         return null;

    //     // return 'images/news/'.rawurlencode($attr);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/' . $attr);

    // }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'groups_news', 'news_id', 'group_group_id')->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(AppUser::class, 'users_news', 'news_id', 'user_id')->withTimestamps();
    }
}
