<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_links', function (Blueprint $table) {
            // A fixed preset key (facebook, instagram, ...) picked from a visual
            // icon selector in the admin, instead of uploading a custom image —
            // guarantees a real, recognizable brand icon every time.
            $table->string('icon_key')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('social_links', function (Blueprint $table) {
            $table->dropColumn('icon_key');
        });
    }
};
