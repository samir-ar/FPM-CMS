<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_guest_votes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('poll_id');
            $table->unsignedBigInteger('option_id');
            $table->string('device_id');
            $table->foreign('poll_id')->references('id')->on('polls');
            $table->foreign('option_id')->references('id')->on('polls_options');
            $table->unique(['poll_id', 'device_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_guest_votes');
    }
};
