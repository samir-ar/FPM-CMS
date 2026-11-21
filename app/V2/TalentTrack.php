<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

// One of the two FPM Talent tracks: 'peer' (تسمية الآخرين) and 'self' (إبداء الرغبة). The CMS decides its name, its open
// window, its audience and its texts; the app only follows.
class TalentTrack extends Model
{
    protected $table = 'talent_tracks';
    protected $guarded = ['id'];
    protected $casts = [
        'is_enabled' => 'boolean',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
    ];

    const PEER = 'peer';
    const SELF = 'self';

    // open | upcoming | closed. Nothing is open until it is enabled in the CMS (and inside its dates, when dates are set).
    public function state(): string
    {
        if (!$this->is_enabled) {
            return 'closed';
        }
        $now = now();
        if ($this->opens_at && $now->lt($this->opens_at)) {
            return 'upcoming';
        }
        if ($this->closes_at && $now->gt($this->closes_at)) {
            return 'closed';
        }

        return 'open';
    }

    // The peer track is always members only; the CMS can only choose members / public for the self track.
    public function audienceFor(): string
    {
        return $this->code === self::PEER ? 'members' : ($this->audience === 'public' ? 'public' : 'members');
    }

    public function visibleTo(bool $isMember): bool
    {
        return $isMember || $this->audienceFor() === 'public';
    }
}
