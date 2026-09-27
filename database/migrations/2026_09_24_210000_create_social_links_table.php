<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_links', function (Blueprint $table) {
            $table->engine = 'InnoDB'; // local WAMP defaults to MyISAM otherwise
            $table->id();
            $table->string('platform'); // e.g. Facebook, Instagram, WhatsApp — admin-entered label
            $table->string('url');
            $table->string('icon')->nullable(); // uploaded image, same convention as business_type_icons
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_links');
    }
};
