<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportMukhtars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mukhtars:import {file : Full path to the Excel file}';
    protected $description = 'Import mukhtars from Excel file into mukhtars table';

    public function handle()
    {
        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return 1;
        }
        $this->info("Importing from: $file");
        \App\V2\Mukhtar::query()->delete();
        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\MukhtarsImport(), $file);
        $count = \App\V2\Mukhtar::count();
        $this->info("Import complete — $count records inserted.");
        return 0;
    }
}
