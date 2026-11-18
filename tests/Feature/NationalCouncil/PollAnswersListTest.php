<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// The admin "User Polls" list of one regular poll: member answers AND guest answers, with the member id, the device
// model and the hardware id (the phone's physical id).
class PollAnswersListTest extends CouncilTestCase
{
    public function test_the_list_shows_member_and_guest_answers_with_member_id_and_hardware_id(): void
    {
        auth('admin')->setUser($this->adminUser());
        $now = now();

        $pollId = DB::table('polls')->insertGetId(['question' => json_encode(['ar' => 'T answers poll', 'en' => 'T answers poll']), 'show' => 1, 'show_for_guest' => 1, 'expiry_date' => now()->addDay(), 'created_at' => $now, 'updated_at' => $now]);
        $optA = DB::table('polls_options')->insertGetId(['poll_id' => $pollId, 'option' => json_encode(['ar' => 'Option A', 'en' => 'Option A']), 'created_at' => $now, 'updated_at' => $now]);
        $optB = DB::table('polls_options')->insertGetId(['poll_id' => $pollId, 'option' => json_encode(['ar' => 'Option B', 'en' => 'Option B']), 'created_at' => $now, 'updated_at' => $now]);

        $member = $this->makeAppUser('T Answering Member', '5620000001', ['phone_number' => '96170620001']);
        $hidden = $this->makeAppUser('T Hidden Account Member', '5620000002', ['phone_number' => '96170620002', 'deleted_at' => $now]);
        DB::table('users_polls')->insert(['app_user_id' => $member, 'poll_id' => $pollId, 'option_id' => $optA, 'device_model' => 'Samsung SM-A525F', 'hardware_id' => 'HW-MEMBER-ONE', 'member_key' => '5620000001', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('users_polls')->insert(['app_user_id' => $hidden, 'poll_id' => $pollId, 'option_id' => $optB, 'member_key' => '5620000002', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('guest_poll_votes')->insert(['device_id' => 'T-guest-dev', 'poll_id' => $pollId, 'option_id' => $optB, 'device_model' => 'Apple iPhone14,5', 'hardware_id' => 'HW-GUEST-ONE', 'created_at' => $now, 'updated_at' => $now]);

        // the page: header and the totals (members + guests)
        $html = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.userPolls.index', ['poll_id' => $pollId], false))->assertOk()->getContent();
        $this->assertStringContainsString('Hardware ID', $html);
        $this->assertStringContainsString('Member ID', $html);
        $this->assertStringContainsString('Total Voters: 3 (members 2, guests 1)', $html);

        // the table data
        $res = $this->actingAs($this->adminUser(), 'admin')->getJson(
            route('admin.userPolls.index', ['poll_id' => $pollId, 'draw' => 1, 'start' => 0, 'length' => 50], false),
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();
        $rows = collect($res->json('data'));
        $this->assertCount(3, $rows);

        $m = $rows->firstWhere('hardware_id', 'HW-MEMBER-ONE');
        $this->assertSame('Member', $m['kind']);
        $this->assertSame('5620000001', $m['member_id']);
        $this->assertSame('Samsung SM-A525F', $m['device_model']);
        $this->assertStringContainsString('T Answering Member', $m['user']);

        $g = $rows->firstWhere('hardware_id', 'HW-GUEST-ONE');
        $this->assertSame('Guest', $g['kind']);
        $this->assertNull($g['member_id']);
        $this->assertSame('Guest', $g['user']);

        $h = $rows->firstWhere('member_id', '5620000002');
        $this->assertNotNull($h, 'an answer from a hidden account still shows its member');
        $this->assertNull($h['hardware_id'], 'an older answer without a hardware id shows empty');
    }
}
