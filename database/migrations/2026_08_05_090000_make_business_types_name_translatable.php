<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeBusinessTypesNameTranslatable extends Migration
{
    public function up()
    {
        foreach (DB::table('business_types')->get() as $type) {
            $decoded = json_decode($type->name, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                continue;
            }

            DB::table('business_types')->where('id', $type->id)->update([
                'name' => json_encode(['ar' => $type->name], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down()
    {
        foreach (DB::table('business_types')->get() as $type) {
            $decoded = json_decode($type->name, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                DB::table('business_types')->where('id', $type->id)->update([
                    'name' => $decoded['ar'] ?? $decoded['en'] ?? $type->name,
                ]);
            }
        }
    }
}
