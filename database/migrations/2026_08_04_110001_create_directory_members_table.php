<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDirectoryMembersTable extends Migration
{
    public function up()
    {
        Schema::create('directory_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_type_id');
            $table->string('name');
            $table->string('specialty')->nullable();
            $table->string('phone')->nullable();
            $table->string('image')->nullable();
            $table->integer('order')->nullable();
            $table->timestamps();

            $table->index('business_type_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('directory_members');
    }
}
