<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class PollGuestVote extends Model
{
    protected $fillable = ['poll_id', 'option_id', 'device_id'];

    public function poll()
    {
        return $this->belongsTo(Poll::class, 'poll_id');
    }

    public function option()
    {
        return $this->belongsTo(PollOption::class, 'option_id');
    }
}
