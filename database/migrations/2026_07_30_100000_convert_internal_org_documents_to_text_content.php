<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_org_documents', function (Blueprint $table) {
            $table->dropColumn(['title', 'file_name', 'order']);
            $table->longText('content')->nullable()->after('tab');
            $table->unique('tab');
        });
    }

    public function down(): void
    {
        Schema::table('internal_org_documents', function (Blueprint $table) {
            $table->dropUnique(['tab']);
            $table->dropColumn('content');
            $table->string('title')->nullable();
            $table->string('file_name')->nullable();
            $table->integer('order')->default(0);
        });
    }
};
