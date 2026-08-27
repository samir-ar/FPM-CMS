<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Traits\BirthdayWishesTrait;

class SendBirthdayWishes extends Command
{
    use BirthdayWishesTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'birthdays:send-wishes {--date= : Override "today" as YYYY-MM-DD, for testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends a Happy Birthday push + in-app notification to every member whose birthday is today';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $count = $this->sendTodaysBirthdayWishes($this->option('date'));

        $this->info("Sent birthday wishes to {$count} member(s).");

        return 0;
    }
}
