<?php

namespace App\Console;

use App\Console\Commands\GetGroups;
use App\Console\Commands\SendBirthdayWishes;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        GetGroups::class,
        SendBirthdayWishes::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();

        $schedule->command('birthdays:send-wishes')->dailyAt('09:00');
    }

    /**
     * Timezone the scheduler uses to evaluate dailyAt()/etc. Laravel defaults
     * to UTC otherwise, which would mean 09:00 UTC instead of 09:00 Beirut.
     *
     * @return string
     */
    protected function scheduleTimezone()
    {
        return config('app.timezone');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
