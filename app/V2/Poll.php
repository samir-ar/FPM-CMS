<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Http\Traits\MyTranslationTrait;


class Poll extends Model
{
    use SoftDeletes;
    use MyTranslationTrait;

    public $translatable = ['question', 'details'];


    public function groups()
    {
        return $this->belongsToMany(Group::class, 'groups_polls', 'poll_id', 'group_group_id')
            ->withTimestamps();
    }

    public function options()
    {
        return $this->hasMany(PollOption::class, 'poll_id', 'id');
    }

    public function users()
    {
        return $this->belongsToMany(AppUser::class, 'users_polls','poll_id', 'app_user_id');
    }
}
