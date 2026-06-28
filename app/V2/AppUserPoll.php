<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class AppUserPoll extends Model
{
    protected $table='users_polls';

    public function user()
    {
        return $this->belongsTo(AppUser::class, 'app_user_id', 'id');
    }

    public function option()
    {
        return $this->belongsTo(PollOption::class, 'option_id', 'id');
    }

    public function poll()
    {
        return $this->belongsTo(Poll::class, 'poll_id', 'id');
    }
}
