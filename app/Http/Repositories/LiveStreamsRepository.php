<?php


namespace App\Http\Repositories;


use App\LiveStream;
use App\Setting;
use App\Http\Repositories\SettingsRepository;

class LiveStreamsRepository
{

    public function initialize()
    {
        $first = LiveStream::first();

        if(!$first){
            LiveStream::create([]);
        }
    }

    public function getInstance(LiveStream $stream = null)
    {
        $settingRepo = new SettingsRepository();
        $settingRepo->initialize();

        return [
            'is_stream_active' => $stream ? Setting::where('slug','live_stream')->first()->bool_flag : false,
            'id' => $stream ? $stream->id : null,
            'stream' => $stream ? $stream->live_stream : null,
        ];
    }
}