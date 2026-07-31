<?php

namespace App\Console\Commands;

use App\Imports\MunicipalityMembersImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ImportMunicipalityMembers extends Command
{
    protected $signature = 'municipalities:import {file : Full path to the Excel file}';
    protected $description = 'Import municipality members from Excel file into municipality_members table';

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return 1;
        }

        $this->info("Importing from: $file");
        \App\V2\MunicipalityMember::query()->delete();
        Excel::import(new MunicipalityMembersImport(), $file);
        $this->info('Import complete.');

        $count = \App\V2\MunicipalityMember::count();
        $this->info("Total records inserted: $count");
        return 0;
    }
}
