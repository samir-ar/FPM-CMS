<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\MyTranslationTrait;
use Illuminate\Database\Eloquent\SoftDeletes;


class InternalProcess extends Model
{
    use MyTranslationTrait;
    use SoftDeletes;


    protected $table = 'internal_process'; 

    public $translatable = ['name','description'];

    protected $fillable = [
        'name',
        'description',
        'link',
    ];

    protected $hidden = ['created_at','updated_at','deleted_at'];

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'groups_laws', 'law_id', 'group_group_id')->withTimestamps();
    }
}
