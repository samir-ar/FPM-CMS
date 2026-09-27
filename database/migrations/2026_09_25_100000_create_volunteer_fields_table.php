<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_fields', function (Blueprint $table) {
            $table->engine = 'InnoDB'; // local WAMP defaults to MyISAM, which silently drops the FK below
            $table->id();
            $table->unsignedBigInteger('volunteer_id');
            $table->string('name'); // translatable (spatie-style JSON, matching Volunteer::title/text)
            $table->unsignedInteger('needed_count')->nullable(); // how many people are needed in this field; null = unspecified
            $table->timestamps();

            $table->foreign('volunteer_id')->references('id')->on('volunteers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_fields');
    }
};
