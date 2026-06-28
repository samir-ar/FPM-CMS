<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    public $primaryKey = 'group_id';

    public function memos()
    {
        return $this->belongsToMany(Memo::class, 'groups_memos','group_group_id', 'memo_id');
    }

    public function news()
    {
        return $this->belongsToMany(News::class, 'groups_news', 'group_group_id', 'news_id');
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'groups_events', 'group_group_id', 'event_id');
    }

    public function links()
    {
        return $this->belongsToMany(Link::class, 'groups_links', 'group_group_id', 'link_id');
    }

    public function liveStreams()
    {
        return $this->belongsToMany(LiveStream::class, 'live_streams', 'group_group_id', 'live_stream_id');
    }
}
