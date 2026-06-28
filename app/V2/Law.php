<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Http\Traits\MyTranslationTrait;
use Storage;
class Law extends Model
{
    use SoftDeletes;
    use MyTranslationTrait;

    public $translatable = ['name'];

    protected $fillable = [
        'name',
        'details',
        'date',
        'order',
        'file',
        'status',
    ];

    // public function getFileAttribute($attr)
    // {
    //     if(!$attr)
    //         return null;

    //     // return 'images/laws/'.rawurlencode($attr);
    //     return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/laws/' . $attr);

    // }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'groups_laws', 'law_id', 'group_group_id')->withTimestamps();
    }
}
