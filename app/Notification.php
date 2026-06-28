<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Storage;
class Notification extends Model
{
    protected $table='notifications';

    // public function getImageAttribute($attr)
    // {
    //     if(!$attr)
    //         return null;

    //     // return 'images/notifications/'.rawurlencode($attr);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/notifications_images/' . $attr);

    // }

    public function users()
    {
        return $this->belongsToMany(AppUser::class, 'users_notifications', 'notification_id', 'id')
            ->withPivot('viewed')->withTimestamps();
    }
}
