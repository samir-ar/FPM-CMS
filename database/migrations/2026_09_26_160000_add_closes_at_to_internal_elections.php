<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddClosesAtToInternalElections extends Migration
{
    public function up()
    {
        Schema::table('internal_elections', function (Blueprint $table) {
            $table->dateTime('closes_at')->nullable()->after('is_active');
        });
    }

    public function down()
    {
        Schema::table('internal_elections', function (Blueprint $table) {
            $table->dropColumn('closes_at');
        });
    }
}
