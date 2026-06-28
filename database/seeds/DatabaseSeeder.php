<?php

use Illuminate\Database\Seeder;
use App\User;

class DatabaseSeeder extends Seeder
{

    public $tables = ['users'];

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
       /*  DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach($this->tables as $table){
            //DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $user = new User;
        $user->name = 'admin';
        $user->email = 'admin@admin.com';
        $user->password = Hash::make('123123');
        $user->save(); */
        //$this->call(PagesSeeder::class);
        // $this->call(UsersTableSeeder::class);
        //$this->call(DistrictsRegionsSeeder::class);
        //$this->call(SeedCommittees::class);
        $this->call(CommitteesPostsSeeder::class);
    }
}
