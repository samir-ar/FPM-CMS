<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\MyTranslationTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storage;

class Event extends Model
{
    use MyTranslationTrait;
    use SoftDeletes;

    public $translatable = ['name', 'details'];


    // public function getImageAttribute($attr)
    // {
    //     if(!$attr)
    //         return null;

    //     // return 'images/events/'.rawurlencode($attr);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/events/' . $attr);

    // }

    public function images()
    {
        return $this->hasMany(EventImage::class, 'event_id', 'id');
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'groups_events', 'event_id', 'group_group_id')
            ->withTimestamps();
    }
}
