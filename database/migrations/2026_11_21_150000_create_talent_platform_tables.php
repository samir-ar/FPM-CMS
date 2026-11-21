<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// FPM Talent (منصة الكفاءات), redesigned: two TRACKS (peer nomination "تسمية الآخرين", self-interest "إبداء الرغبة"), each
// with its own open window + audience (members only / public) edited in the CMS; a roles list the member ticks from;
// an FAQ per language; submissions with a review status; private CV / document files. The old vacancy tables stay
// untouched (older app builds still use them).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talent_tracks', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->string('code', 8)->unique();                       // peer | self
            $t->string('title', 191);
            $t->string('subtitle', 255)->nullable();
            $t->text('intro')->nullable();                         // the welcome text above the form
            $t->text('acknowledgement')->nullable();               // the sentence the member must tick
            $t->text('closed_message')->nullable();                // shown on the locked card
            $t->boolean('is_enabled')->default(false);             // nothing opens by accident
            $t->dateTime('opens_at')->nullable();
            $t->dateTime('closes_at')->nullable();
            $t->string('audience', 8)->default('members');         // members | public
            $t->unsignedInteger('max_per_member')->default(10);    // per cycle
            $t->unsignedInteger('cycle')->default(1);
            $t->timestamps();
        });

        Schema::create('talent_roles', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->string('name', 191);
            $t->boolean('allows_text')->default(false);            // "any other field ____"
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('talent_faqs', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->string('language', 2);                             // ar | en
            $t->text('question');
            $t->text('answer');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('talent_submissions', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->string('track', 8);
            $t->unsignedInteger('cycle')->default(1);
            $t->unsignedBigInteger('user_id')->nullable();         // app_users.id (members)
            $t->string('member_id', 32)->nullable();
            $t->boolean('is_guest')->default(false);
            $t->string('submitter_name', 191)->nullable();
            $t->string('submitter_phone', 32)->nullable();
            $t->string('submitter_email', 191)->nullable();
            $t->string('submitter_district', 191)->nullable();
            $t->string('submitter_town', 191)->nullable();
            $t->string('submitter_role', 191)->nullable();         // organizational role, if any
            // peer track
            $t->string('nominee_name', 191)->nullable();
            $t->string('profession', 191)->nullable();
            $t->string('relation_to_fpm', 255)->nullable();
            $t->text('relationship_between')->nullable();
            $t->text('reasons')->nullable();
            $t->text('strengths')->nullable();
            // self track
            $t->text('personal_info')->nullable();
            $t->text('academic')->nullable();
            $t->text('professional')->nullable();
            $t->text('organizational')->nullable();
            $t->text('interests')->nullable();
            $t->text('cover_letter')->nullable();
            $t->text('roles_json')->nullable();                    // [{id,name,other}]
            $t->boolean('acknowledged')->default(false);
            $t->string('status', 16)->default('new');              // new | shortlisted | accepted | rejected
            $t->text('admin_notes')->nullable();
            $t->string('device_id', 191)->nullable();
            $t->string('hardware_id', 191)->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
            $t->index(['track', 'cycle', 'user_id']);
            $t->index(['track', 'cycle', 'submitter_phone']);
            $t->index('status');
        });

        Schema::create('talent_attachments', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->unsignedBigInteger('submission_id')->index();
            $t->string('kind', 4);                                 // cv | doc
            $t->string('original_name', 191);
            $t->string('path', 255);                               // on the PRIVATE local disk, never public
            $t->string('mime', 100)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->timestamps();
        });

        $now = now();
        DB::table('talent_tracks')->insert([
            [
                'code' => 'peer',
                'title' => 'تسمية الآخرين للمساهمة في أعمال التيّار',
                'subtitle' => null,
                'intro' => self::INTRO,
                'acknowledgement' => self::ACK,
                'closed_message' => 'باب التسمية مقفل حالياً.',
                'is_enabled' => false,
                'audience' => 'members',
                'max_per_member' => 10,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'self',
                'title' => 'إبداء الرغبة بالمساهمة في أعمال التيّار',
                'subtitle' => null,
                'intro' => self::INTRO,
                'acknowledgement' => self::ACK,
                'closed_message' => 'باب إبداء الرغبة مقفل حالياً.',
                'is_enabled' => false,
                'audience' => 'members',
                'max_per_member' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        // the owner's list (last one lets the member write his own words); editable in the CMS (Talent Roles)
        $roles = ['الشؤون البلدية والاجتماعية', 'التدريب والتثقيف', 'الشؤون الانتخابية', 'الإعلام والتواصل', 'التنظيم والتعبئة', 'الاقتصاد والمال', 'الشؤون القانونية', 'الانتشار والاغتراب', 'أخرى'];
        foreach ($roles as $i => $name) {
            DB::table('talent_roles')->insert([
                'name' => $name, 'allows_text' => $i === count($roles) - 1, 'is_active' => true, 'sort_order' => $i + 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_attachments');
        Schema::dropIfExists('talent_submissions');
        Schema::dropIfExists('talent_faqs');
        Schema::dropIfExists('talent_roles');
        Schema::dropIfExists('talent_tracks');
    }

    private const INTRO = "اهلا بك في منصة الكفاءات.\nيُعتبر تقديم الطلب بمثابة إبداء رغبة واهتمام فقط لا غير، ولا يضمن تقديم الطلب اختيار المتقدم/ة أو من ي/تسميه/ا، أو تبنيه/ا، أو تسميته/ا رسمياً. يحتفظ التيار بالحق المطلق في رفض أي طلب مقدم، ويعود القرار النهائي في الاختيار حصراً لقيادة التيّار.";

    private const ACK = 'أُقر انّ جميع المعلومات الواردة في هذه الاستمارة صحيحة وكاملة، وأتحمّل مسؤولية أيّ معلومة غير دقيقة، وأوافق على استخدامها من قبل اللجنة المركزية للموارد البشرية لأغراض دراسة الطلب حصراً.';
};
