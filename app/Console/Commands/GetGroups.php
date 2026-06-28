<?php

namespace App\Console\Commands;

use DB;
use App\Group;
use Illuminate\Console\Command;
use App\Http\Repositories\FpmApisRepository;

class GetGroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'groups:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get the groups from fpm';

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
     * @return mixed
     */
    public function handle()
    {
        $fpm = new FpmApisRepository();
        $groups = $fpm->getGroups();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('groups')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        foreach($groups as $g){

            $group = new Group();

            $group->group_id = $g->GroupId;

            $group->name = $g->GroupName;

            $group->save();

        }

    }
}
