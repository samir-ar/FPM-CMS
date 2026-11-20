<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Http\Traits\MyTranslationTrait;


class PollOption extends Model
{
    use SoftDeletes;
    use MyTranslationTrait;

    public $translatable = ['option'];

    // a member's written answer on an option that takes text: at most this many characters (app and server)
    const TEXT_MAX = 300;


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
