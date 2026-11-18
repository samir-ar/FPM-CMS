<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Controllers\Api\V2\ApiController;
use App\Http\Repositories\V2\ApiRepository;
use App\V2\AppUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// A regular poll answered by a logged-in member counts once per MEMBER, whichever app account sends it.
class PollMemberAnswerTest extends CouncilTestCase
{
    private $lastBody = '';

    private function answer(int $userId, int $optionId): int
    {
        $req = Request::create('/api/v2/answer-poll', 'POST', ['option_id' => $optionId]);
        $req->merge(['user' => AppUser::find($userId)]);
        app()->instance('request', $req);   // answerPoll reads the global request() helper

        $res = app(ApiController::class)->answerPoll($req, app(ApiRepository::class));
        $this->lastBody = $res->getContent();

        return $res->getStatusCode();
    }

    private function answers(int $pollId): int
    {
        return DB::table('users_polls')->where('poll_id', $pollId)->count();
    }

    public function test_a_member_answers_a_poll_only_once_across_all_their_accounts(): void
    {
        $now = now();
        $pollId = DB::table('polls')->insertGetId([
            'question' => json_encode(['ar' => 'T poll', 'en' => 'T poll'], JSON_UNESCAPED_UNICODE), 'show' => 1, 'show_for_guest' => 0,
            'expiry_date' => now()->addDay(), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $o1 = DB::table('polls_options')->insertGetId(['poll_id' => $pollId, 'option' => json_encode(['ar' => 'A', 'en' => 'A']), 'created_at' => $now, 'updated_at' => $now]);
        $o2 = DB::table('polls_options')->insertGetId(['poll_id' => $pollId, 'option' => json_encode(['ar' => 'B', 'en' => 'B']), 'created_at' => $now, 'updated_at' => $now]);

        $first = $this->makeAppUser('T member', '5560000001');
        $second = $this->makeAppUser('T member second account', '5560000001');
        $zero = $this->makeAppUser('T member zero-padded', '05560000001');
        $other = $this->makeAppUser('T other member', '5560000002');

        $this->assertSame(200, $this->answer($first, $o1), $this->lastBody);
        $this->assertSame(1, $this->answers($pollId));

        // same account again, another account, the zero-padded id: all refused, nothing added or changed
        foreach ([$first, $second, $zero] as $u) {
            $this->assertSame(400, $this->answer($u, $o2), 'a member who answered cannot answer again');
        }
        $this->assertSame(1, $this->answers($pollId));
        $this->assertSame($o1, (int) DB::table('users_polls')->where('poll_id', $pollId)->value('option_id'), 'the first answer stays');

        // a different member is not affected
        $this->assertSame(200, $this->answer($other, $o2));
        $this->assertSame(2, $this->answers($pollId));
    }

    private function guestAnswer(int $optionId, string $deviceId, ?string $hardwareId): int
    {
        $req = Request::create('/api/v2/answer-poll', 'POST', ['option_id' => $optionId, 'device_id' => $deviceId] + ($hardwareId ? ['hardware_id' => $hardwareId] : []));
        app()->instance('request', $req);

        return app(ApiController::class)->answerPoll($req, app(ApiRepository::class))->getStatusCode();
    }

    public function test_a_guest_counts_once_per_phone_not_per_app_install(): void
    {
        $now = now();
        $pollId = DB::table('polls')->insertGetId([
            'question' => json_encode(['ar' => 'T guest poll', 'en' => 'T guest poll']), 'show' => 1, 'show_for_guest' => 1,
            'expiry_date' => now()->addDay(), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $o = DB::table('polls_options')->insertGetId(['poll_id' => $pollId, 'option' => json_encode(['ar' => 'A', 'en' => 'A']), 'created_at' => $now, 'updated_at' => $now]);

        $this->assertSame(200, $this->guestAnswer($o, 'T-dev-1', 'T-phone-1'));
        // app data cleared / reinstalled: new device_id, same phone -> refused
        $this->assertSame(400, $this->guestAnswer($o, 'T-dev-2', 'T-phone-1'));
        // same app install again -> refused
        $this->assertSame(400, $this->guestAnswer($o, 'T-dev-1', 'T-phone-1'));
        $this->assertSame(1, DB::table('guest_poll_votes')->where('poll_id', $pollId)->count());
        // a different phone is a different guest
        $this->assertSame(200, $this->guestAnswer($o, 'T-dev-3', 'T-phone-2'));
        // after reinstalling, the guest still sees this poll as answered (found by the phone, not the install)
        $repo = app(ApiRepository::class);
        $groupId = DB::table('groups')->value('id');
        DB::table('groups_polls')->insert(['poll_id' => $pollId, 'group_group_id' => 81, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertCount(1, $repo->getPreviousPolls(null, [$groupId], 'T-dev-2', 'T-phone-1'), 'found by hardware id');
        $this->assertCount(0, $repo->getPreviousPolls(null, [$groupId], 'T-dev-unknown', null), 'a phone we have never seen has none');
        // an old app build that sends no hardware id cannot vote as a guest at all (it has to update)
        $this->assertSame(400, $this->guestAnswer($o, 'T-dev-4', null));
        $this->assertSame(2, DB::table('guest_poll_votes')->where('poll_id', $pollId)->count());
    }
}
