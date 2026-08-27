<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The original migration's foreign() calls silently no-op'd because
        // this table (and checkin_events, just created) landed on MyISAM
        // (this DB's default engine), not InnoDB — MyISAM doesn't support FK
        // constraints, and InnoDB can't reference a MyISAM table either. Fix
        // both engines first, then the constraints actually take effect.
        DB::statement('ALTER TABLE event_attendance ENGINE=InnoDB');
        DB::statement('ALTER TABLE checkin_events ENGINE=InnoDB');

        // Environments diverge here: some already had a real FK (event_id ->
        // events) from the original migration succeeding on InnoDB; others
        // never got one because MyISAM silently no-op'd it. Drop whatever
        // FK currently sits on each column (if any) before adding the new
        // one, so this migration is safe to run regardless of prior state.
        $this->dropExistingForeignKeys('event_attendance', ['event_id', 'checked_in_by_app_user_id']);

        Schema::table('event_attendance', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('checkin_events')->cascadeOnDelete();
            $table->foreign('checked_in_by_app_user_id')->references('id')->on('app_users')->cascadeOnDelete();
        });
    }

    private function dropExistingForeignKeys(string $table, array $columns): void
    {
        $constraints = DB::select(
            "SELECT CONSTRAINT_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_NAME = ? AND COLUMN_NAME IN (" . implode(',', array_fill(0, count($columns), '?')) . ")
             AND REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_SCHEMA = DATABASE()",
            [$table, ...$columns]
        );

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_attendance', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropForeign(['checked_in_by_app_user_id']);
        });
    }
};
