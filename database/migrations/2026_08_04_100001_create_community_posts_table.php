<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommunityPostsTable extends Migration
{
    public function up()
    {
        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('business_type_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->unsignedInteger('age')->nullable();
            $table->string('email')->nullable();
            $table->text('business_description')->nullable();
            $table->string('image')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            $table->index('user_id');
            $table->index('business_type_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('community_posts');
    }
}
