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
}
