<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Http\Traits\MyTranslationTrait;


class PollOption extends Model
{
    use SoftDeletes;
    use MyTranslationTrait;

    public $translatable = ['option'];


    protected $table='polls_options';

    public function poll()
    {
        return $this->belongsTo(Poll::class, 'poll_id', 'id');
    }

    public function answers()
    {
        return $this->hasMany(AppUserPoll::class, 'option_id', 'id');
    }
}
