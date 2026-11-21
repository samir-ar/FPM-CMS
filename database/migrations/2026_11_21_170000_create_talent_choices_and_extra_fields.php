<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// FPM Talent drop-down lists that the CMS can add to / remove from (page "Talent Choices"), plus the extra fields the
// forms ask for: the nominee's district and town (naming form) and the education level (self form).
//   fpm_relation    = the nominee's relation to the party
//   person_relation = the relation between the person who names and the nominee
//   education       = education level
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talent_choices', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->string('group', 32)->index();
            $t->string('name', 191);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        $lists = [
            'fpm_relation' => ['ملتزم/ة في التيار', 'مؤيد/ة للتيار', 'مستقل/ة'],
            'person_relation' => ['علاقة قربة', 'علاقة صداقة', 'علاقة مهنية', 'علاقة حزبية'],
            'education' => ['ثانوي', 'معهد تقني', 'اجازة جامعية', 'ماجيستير', 'دكتوراه'],
        ];
        $now = now();
        foreach ($lists as $group => $names) {
            foreach ($names as $i => $name) {
                DB::table('talent_choices')->insert(['group' => $group, 'name' => $name, 'is_active' => true, 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        Schema::table('talent_submissions', function (Blueprint $t) {
            $t->string('nominee_district', 191)->nullable()->after('nominee_name');
            $t->string('nominee_town', 191)->nullable()->after('nominee_district');
            $t->string('education_level', 191)->nullable()->after('academic');
        });
    }

    public function down(): void
    {
        Schema::table('talent_submissions', function (Blueprint $t) {
            $t->dropColumn(['nominee_district', 'nominee_town', 'education_level']);
        });
        Schema::dropIfExists('talent_choices');
    }
};
