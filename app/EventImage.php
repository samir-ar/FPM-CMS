<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Storage;

class EventImage extends Model
{
    protected $table = 'event_images';

    // public function getSrcAttribute($attr)
    // {
    //     if(!$attr)
    //         return null;

    //     // return 'images/events/'.rawurlencode($attr);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/events/' . $attr);

    // }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }
}
