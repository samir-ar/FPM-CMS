<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE municipality_members MODIFY position ENUM('رئيس', 'نائب رئيس', 'عضو', 'متوفي') NOT NULL DEFAULT 'عضو'");
        DB::statement("ALTER TABLE mukhtars MODIFY position ENUM('مختار', 'عضو اختياري', 'متوفي') NOT NULL DEFAULT 'مختار'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE municipality_members MODIFY position ENUM('رئيس', 'نائب رئيس', 'عضو') NOT NULL DEFAULT 'عضو'");
        DB::statement("ALTER TABLE mukhtars MODIFY position ENUM('مختار', 'عضو اختياري') NOT NULL DEFAULT 'مختار'");
    }
};
