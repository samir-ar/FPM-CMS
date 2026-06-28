<?php


namespace App\Http\Repositories;

use App\Setting;

class SettingsRepository
{
    public function initialize()
    {
        $has_live_stream = Setting::where('slug', 'live_stream')->first();

        if(!$has_live_stream){
            $has_live_stream = Setting::create([
                'slug' => 'live_stream',
                'title' => null,
                'text' => null,
            ]);
        }
    }
}