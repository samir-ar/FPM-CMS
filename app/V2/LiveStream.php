<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class LiveStream extends Model
{
    protected $table='live_streams';

    protected $guarded = ['id'];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function groups()
    {
        return $this->belongsToMany(Group::class,'live_streams_groups','live_stream_id', 'group_group_id')
            ->withTimestamps();
    }
}
