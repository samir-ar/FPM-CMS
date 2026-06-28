<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Http\Traits\MyTranslationTrait;
use Storage;

class Memo extends Model
{
    use SoftDeletes;
    use MyTranslationTrait;

    public $translatable = ['name'];


    // public function getFileAttribute($attr)
    // {
    //     if(!$attr)
    //         return null;

    //     // return 'images/memos/'.rawurlencode($attr);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/memos/' . $attr);

    // }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'groups_memos', 'memo_id', 'group_group_id')->withTimestamps();
    }
}
