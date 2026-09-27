<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class GuestPollVote extends Model
{
    protected $table = 'guest_poll_votes';
    protected $fillable = ['device_id', 'poll_id', 'option_id'];

    public function option()
    {
        return $this->belongsTo(PollOption::class, 'option_id', 'id');
    }

    public function poll()
    {
        return $this->belongsTo(Poll::class, 'poll_id', 'id');
    }
}
