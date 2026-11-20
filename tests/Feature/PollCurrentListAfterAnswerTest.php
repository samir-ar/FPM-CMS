<?php

namespace Tests\Feature;

use App\Http\Repositories\V2\ApiRepository;
use App\V2\AppUser;
use Illuminate\Support\Facades\DB;
use Tests\Feature\NationalCouncil\CouncilTestCase;

// The "current polls" list is reloaded right after a member answers a poll. It crashed (undefined $hardwareId) for any
// member who had already answered, for guests too, because the phone's hardware id was not passed into the list builder.
class PollCurrentListAfterAnswerTest extends CouncilTestCase
{
    private function poll(): array
    {
        $now = now();
        $id = DB::table('polls')->insertGetId(['question' => json_encode(['ar' => 'T poll', 'en' => 'T poll']), 'show' => 1, 'show_for_guest' => 1, 'expiry_date' => $now->copy()->addDay(), 'created_at' => $now, 'updated_at' => $now]);
        $a = DB::table('polls_options')->insertGetId(['poll_id' => $id, 'option' => json_encode(['ar' => 'نعم', 'en' => 'Yes']), 'created_at' => $now, 'updated_at' => $now]);
        DB::table('polls_options')->insert(['poll_id' => $id, 'option' => json_encode(['ar' => 'لا', 'en' => 'No']), 'created_at' => $now, 'updated_at' => $now]);
        DB::table('groups_polls')->insert(['poll_id' => $id, 'group_group_id' => 81, 'created_at' => $now, 'updated_at' => $now]);

        return [$id, $a];
    }

    public function test_the_list_loads_for_a_member_who_already_answered(): void
    {
        [$pollId, $optionId] = $this->poll();
        $userId = $this->makeAppUser('T answered', '5630000001');
        DB::table('users_polls')->insert(['app_user_id' => $userId, 'poll_id' => $pollId, 'option_id' => $optionId, 'member_key' => '5630000001', 'created_at' => now(), 'updated_at' => now()]);

        $user = AppUser::find($userId);
        $polls = app(ApiRepository::class)->getPollsByGroups($user, [81], 'dev-1', 'HW-1');
        $mine = $polls->firstWhere('id', $pollId);

        $this->assertNotNull($mine);
        $this->assertTrue($mine['already_voted']);
        $this->assertCount(2, $mine['options']);   // the options come back with their results
    }

    public function test_the_list_loads_for_a_guest_who_already_answered(): void
    {
        [$pollId, $optionId] = $this->poll();
        DB::table('guest_poll_votes')->insert(['device_id' => 'T-old-dev', 'poll_id' => $pollId, 'option_id' => $optionId, 'hardware_id' => 'HW-GUEST', 'created_at' => now(), 'updated_at' => now()]);

        // a reinstalled app: new device id, same phone (hardware id)
        $polls = app(ApiRepository::class)->getPollsByGroups(null, [81], 'T-new-dev', 'HW-GUEST');
        $mine = $polls->firstWhere('id', $pollId);

        $this->assertNotNull($mine);
        $this->assertTrue($mine['already_voted']);
    }
}
