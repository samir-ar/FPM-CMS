<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The round icon on each Home card, chosen per card TYPE (NEWS / EVENT / POLL) from the CMS page "Home card icons".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_card_icons', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('type', 16)->unique();
            $table->string('image')->nullable();
            // NEWS only: ignore each news item's own logo and always show this icon
            $table->boolean('use_for_all')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_card_icons');
    }
};
