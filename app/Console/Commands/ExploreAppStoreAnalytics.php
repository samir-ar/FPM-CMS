<?php

namespace App\Console\Commands;

use App\Services\Analytics\AppStoreConnectService;
use Illuminate\Console\Command;

class ExploreAppStoreAnalytics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:apple:explore';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump the live App Store Connect analytics report catalog for this app (report names/ids), so dashboard parsing can target real values instead of guesses';

    public function handle(): int
    {
        $service = AppStoreConnectService::make();

        $categories = [
            'APP_USAGE',
            'APP_STORE_ENGAGEMENT',
            'COMMERCE',
            'FRAMEWORK_USAGE',
            'PERFORMANCE',
        ];

        $catalog = $service->exploreReports($categories);

        foreach ($catalog as $category => $data) {
            $this->info("== {$category} (request {$data['reportRequestId']}) ==");
            foreach ($data['reports'] as $report) {
                $this->line("  [{$report['id']}] {$report['name']}");
            }
        }

        return self::SUCCESS;
    }
}
