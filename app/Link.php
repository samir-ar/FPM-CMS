<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\MyTranslationTrait;
use Illuminate\Database\Eloquent\SoftDeletes;



class Link extends Model
{
    use MyTranslationTrait;
    use SoftDeletes;

    protected $table='important_links';

    public $translatable = ['name'];


    public function groups()
    {
        return $this->belongsToMany(Group::class, 'groups_links', 'link_id', 'group_group_id');
    }
}
