<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

class PollFormSaveTest extends CouncilTestCase
{
    public function test_saving_the_edit_form_keeps_the_tick_and_adds_a_new_text_option(): void
    {
        $now = now();
        $poll = DB::table('polls')->insertGetId(['question' => json_encode(['ar' => 'T save', 'en' => 'T save']), 'show' => 1, 'expiry_date' => $now->copy()->addDay(), 'created_at' => $now, 'updated_at' => $now]);
        $a = DB::table('polls_options')->insertGetId(['poll_id' => $poll, 'option' => json_encode(['ar' => 'نعم', 'en' => 'Yes']), 'allows_text' => 0, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('groups_polls')->insert(['poll_id' => $poll, 'group_group_id' => 81, 'created_at' => $now, 'updated_at' => $now]);

        $res = $this->actingAs($this->adminUser(), 'admin')->put(route('admin.polls.update', $poll, false), [
            'question' => 'T save', 'question_ar' => 'T save', 'expiry_date' => $now->copy()->addDay()->toDateTimeString(), 'groups' => [81], 'show' => 1,
            'options' => [
                ['id' => $a, 'name' => 'Yes', 'name_ar' => 'نعم', 'allows_text' => '1'],
                ['name' => '', 'name_ar' => 'غير ذلك', 'allows_text' => '1'],
            ],
        ]);
        $res->assertSessionHasNoErrors();

        $rows = DB::table('polls_options')->where('poll_id', $poll)->orderBy('id')->pluck('allows_text')->all();
        $this->assertEquals([1, 1], $rows);
    }

    public function test_an_option_with_no_text_at_all_is_refused_with_a_message_on_edit(): void
    {
        $now = now();
        $poll = DB::table('polls')->insertGetId(['question' => json_encode(['ar' => 'T e', 'en' => 'T e']), 'show' => 1, 'expiry_date' => $now->copy()->addDay(), 'created_at' => $now, 'updated_at' => $now]);
        DB::table('groups_polls')->insert(['poll_id' => $poll, 'group_group_id' => 81, 'created_at' => $now, 'updated_at' => $now]);

        $res = $this->actingAs($this->adminUser(), 'admin')->put(route('admin.polls.update', $poll, false), [
            'question' => 'T e', 'question_ar' => 'T e', 'expiry_date' => $now->copy()->addDay()->toDateTimeString(), 'groups' => [81], 'show' => 1,
            'options' => [['name' => 'A', 'name_ar' => 'ا'], ['name' => '', 'name_ar' => '']],
        ]);

        $res->assertSessionHasErrors();
        $this->assertSame(0, DB::table('polls_options')->where('poll_id', $poll)->count());
    }

    public function test_a_text_option_with_one_label_is_saved_for_both_languages(): void
    {
        $now = now();
        $poll = DB::table('polls')->insertGetId(['question' => json_encode(['ar' => 'T f', 'en' => 'T f']), 'show' => 1, 'expiry_date' => $now->copy()->addDay(), 'created_at' => $now, 'updated_at' => $now]);
        DB::table('groups_polls')->insert(['poll_id' => $poll, 'group_group_id' => 81, 'created_at' => $now, 'updated_at' => $now]);

        $this->actingAs($this->adminUser(), 'admin')->put(route('admin.polls.update', $poll, false), [
            'question' => 'T f', 'question_ar' => 'T f', 'expiry_date' => $now->copy()->addDay()->toDateTimeString(), 'groups' => [81], 'show' => 1,
            'options' => [['name' => 'Yes', 'name_ar' => 'نعم'], ['name' => 'Other', 'name_ar' => '', 'allows_text' => '1']],
        ])->assertSessionHasNoErrors();

        $o = DB::table('polls_options')->where('poll_id', $poll)->orderByDesc('id')->first();
        $this->assertSame(['en' => 'Other', 'ar' => 'Other'], json_decode($o->option, true));
        $this->assertEquals(1, $o->allows_text);
    }

    public function test_a_text_option_may_have_no_label_and_the_app_shows_other(): void
    {
        $now = now();
        $poll = DB::table('polls')->insertGetId(['question' => json_encode(['ar' => 'T h', 'en' => 'T h']), 'show' => 1, 'expiry_date' => $now->copy()->addDay(), 'created_at' => $now, 'updated_at' => $now]);
        DB::table('groups_polls')->insert(['poll_id' => $poll, 'group_group_id' => 81, 'created_at' => $now, 'updated_at' => $now]);

        $this->actingAs($this->adminUser(), 'admin')->put(route('admin.polls.update', $poll, false), [
            'question' => 'T h', 'question_ar' => 'T h', 'expiry_date' => $now->copy()->addDay()->toDateTimeString(), 'groups' => [81], 'show' => 1,
            'options' => [['name' => 'Yes', 'name_ar' => 'نعم'], ['name' => '', 'name_ar' => '', 'allows_text' => '1']],
        ])->assertSessionHasNoErrors();

        $o = \App\V2\PollOption::where('poll_id', $poll)->orderByDesc('id')->first();
        $this->assertEquals(1, $o->allows_text);
        $this->assertSame('', (string) $o->getTranslation('option', 'ar'), 'stored without a label');
        $this->assertSame('أخرى', $o->displayLabel('ar'));
        $this->assertSame('Other', $o->displayLabel('en'));
        $mapped = app(\App\Http\Repositories\V2\ApiRepository::class)->getMappedOption(\App\V2\Poll::find($poll), 'ar');
        $this->assertSame('أخرى', $mapped->firstWhere('id', $o->id)->option);
    }

    public function test_create_accepts_a_single_text_option_but_not_a_single_normal_one(): void
    {
        $base = ['question' => 'ZQ single', 'question_ar' => 'ZQ single', 'expiry_date' => now()->addDay()->toDateTimeString(), 'groups' => [81], 'show' => 1];
        $admin = $this->adminUser();

        $this->actingAs($admin, 'admin')->post(route('admin.polls.store', [], false), $base + ['options' => [['name' => 'Yes', 'name_ar' => 'نعم']]])->assertSessionHasErrors();
        $this->assertSame(0, DB::table('polls')->where('question', 'like', '%ZQ single%')->count());

        $this->actingAs($admin, 'admin')->post(route('admin.polls.store', [], false), $base + ['options' => [['name' => '', 'name_ar' => '', 'allows_text' => '1']]])->assertSessionHasNoErrors();
        $poll = DB::table('polls')->where('question', 'like', '%ZQ single%')->first();
        $this->assertNotNull($poll);
        $this->assertSame(1, DB::table('polls_options')->where('poll_id', $poll->id)->count());
    }
}
