<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// FPM Talent: the two forms (naming others / showing interest) are no longer written inside the app. Every section,
// question and special block of a form is a row of this table, edited in the CMS (add, remove, reorder), and the app
// draws the form from it. The rows are seeded with EXACTLY the forms the app has today, so nothing changes until the
// admin edits them. Answers to questions added in the CMS are kept in talent_submissions.extra_json.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('talent_form_items')) {
            Schema::create('talent_form_items', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->id();
                $t->string('track', 8);                                   // peer | self
                $t->string('kind', 12);                                   // section | field | roles | location | cv | docs | guest
                $t->string('key', 40)->nullable();                        // a field: the built-in column name, or x_xxxxxxxx for one added in the CMS
                $t->string('label', 255)->nullable();                     // the title / label shown in the app
                $t->text('hint')->nullable();                             // the small text under it (location: the town label)
                $t->string('input', 10)->default('text');                 // text | textarea | dropdown
                $t->string('choices_key', 32)->nullable();                // a built-in dropdown: the CMS list it uses (talent_choices.list)
                $t->text('options')->nullable();                          // a dropdown added in the CMS: one choice per line
                $t->boolean('required')->default(false);
                $t->string('audience', 8)->default('all');                // all | member | guest
                $t->unsignedTinyInteger('lines')->default(1);             // rows of a long text
                $t->boolean('is_active')->default(true);                  // hidden = not shown in the app, nothing is deleted
                $t->boolean('is_builtin')->default(true);                 // built-in items can be hidden, not deleted
                $t->boolean('is_locked')->default(false);                 // cannot be hidden (the guest identity block)
                $t->unsignedInteger('sort_order')->default(0);
                $t->timestamps();

                $t->index(['track', 'sort_order']);
                $t->unique(['track', 'key']);
            });
        }

        if (Schema::hasTable('talent_submissions') && !Schema::hasColumn('talent_submissions', 'extra_json')) {
            Schema::table('talent_submissions', function (Blueprint $t) {
                $t->longText('extra_json')->nullable();                   // [{key,label,value}] answers to questions added in the CMS
            });
        }

        if (DB::table('talent_form_items')->count() === 0) {
            $this->seed();
        }
    }

    private function seed(): void
    {
        $now = now();
        $rows = [];
        $add = function (string $track, array $item) use (&$rows, $now) {
            static $order = [];
            $order[$track] = ($order[$track] ?? 0) + 1;
            $rows[] = $item + [
                'track' => $track, 'key' => null, 'label' => null, 'hint' => null, 'input' => 'text', 'choices_key' => null, 'options' => null,
                'required' => false, 'audience' => 'all', 'lines' => 1, 'is_active' => true, 'is_builtin' => true, 'is_locked' => false,
                'sort_order' => $order[$track] * 10, 'created_at' => $now, 'updated_at' => $now,
            ];
        };

        // ---- naming others (peer)
        $add('peer', ['kind' => 'section', 'label' => 'الشخص المسمّى']);
        $add('peer', ['kind' => 'field', 'key' => 'nominee_name', 'label' => 'اسم الشخص المسمّى', 'required' => true]);
        $add('peer', ['kind' => 'location', 'label' => 'قضاء الشخص المسمّى', 'hint' => 'بلدة الشخص المسمّى', 'required' => true]);
        $add('peer', ['kind' => 'field', 'key' => 'profession', 'label' => 'المهنة أو الاختصاص', 'required' => true]);
        $add('peer', ['kind' => 'field', 'key' => 'relation_to_fpm', 'label' => 'علاقة الشخص المسمّى بالتيار', 'input' => 'dropdown', 'choices_key' => 'fpm_relation', 'required' => true]);
        $add('peer', ['kind' => 'section', 'label' => 'الأدوار المقترحة له/لها']);
        $add('peer', ['kind' => 'roles', 'hint' => 'يمكنك اختيار أكثر من دور', 'required' => true]);
        $add('peer', ['kind' => 'section', 'label' => 'التفاصيل']);
        $add('peer', ['kind' => 'field', 'key' => 'reasons', 'label' => 'أسباب الاقتراح', 'input' => 'textarea', 'lines' => 4, 'required' => true]);
        $add('peer', ['kind' => 'field', 'key' => 'strengths', 'label' => 'عناصر القوة والمؤهلات', 'input' => 'textarea', 'lines' => 4, 'required' => true]);
        $add('peer', ['kind' => 'field', 'key' => 'relationship_between', 'label' => 'علاقتك بالشخص المسمّى', 'input' => 'dropdown', 'choices_key' => 'person_relation', 'required' => true]);

        // ---- showing interest (self)
        $add('self', ['kind' => 'guest', 'label' => 'معلوماتك الشخصية', 'audience' => 'guest', 'required' => true, 'is_locked' => true]);
        $add('self', ['kind' => 'field', 'key' => 'personal_info', 'label' => 'معلومات شخصية إضافية (اختياري)', 'input' => 'textarea', 'lines' => 2, 'audience' => 'member']);
        $add('self', ['kind' => 'section', 'label' => 'الخبرات والمؤهلات']);
        $add('self', ['kind' => 'field', 'key' => 'education_level', 'label' => 'المستوى التعليمي', 'input' => 'dropdown', 'choices_key' => 'education', 'required' => true]);
        $add('self', ['kind' => 'field', 'key' => 'academic', 'label' => 'المؤهلات العلمية', 'input' => 'textarea', 'lines' => 3, 'required' => true]);
        $add('self', ['kind' => 'field', 'key' => 'professional', 'label' => 'الخبرات المهنية', 'input' => 'textarea', 'lines' => 3, 'required' => true]);
        $add('self', ['kind' => 'field', 'key' => 'organizational', 'label' => 'الخبرات التنظيمية أو العامة', 'input' => 'textarea', 'lines' => 3]);
        $add('self', ['kind' => 'field', 'key' => 'interests', 'label' => 'مجالات الاهتمام', 'input' => 'textarea', 'lines' => 2, 'required' => true]);
        $add('self', ['kind' => 'section', 'label' => 'الأدوار التي ترغب بالمساهمة فيها']);
        $add('self', ['kind' => 'roles', 'hint' => 'يمكنك اختيار أكثر من دور', 'required' => true]);
        $add('self', ['kind' => 'section', 'label' => 'رسالة تعريفية']);
        $add('self', ['kind' => 'field', 'key' => 'cover_letter', 'label' => 'رسالة تعريفية', 'input' => 'textarea', 'lines' => 5, 'required' => true]);
        $add('self', ['kind' => 'section', 'label' => 'السيرة الذاتية والوثائق الداعمة']);
        $add('self', ['kind' => 'cv', 'label' => 'السيرة الذاتية (PDF أو Word، حتى 5 ميغابايت)', 'required' => true]);
        $add('self', ['kind' => 'docs', 'label' => 'وثائق داعمة (اختياري، حتى 3 ملفات: PDF أو صور)']);

        DB::table('talent_form_items')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_form_items');
        if (Schema::hasColumn('talent_submissions', 'extra_json')) {
            Schema::table('talent_submissions', function (Blueprint $t) {
                $t->dropColumn('extra_json');
            });
        }
    }
};
