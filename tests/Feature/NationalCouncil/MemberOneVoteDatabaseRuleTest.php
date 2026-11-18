<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Repositories\V2\ApiRepository;
use App\Support\MemberKey;
use App\V2\AppUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// The database itself refuses a second vote by the same MEMBER (any of their accounts), even when the code-level
// check is skipped - e.g. two accounts of one member voting at the very same instant.
class MemberOneVoteDatabaseRuleTest extends CouncilTestCase
{
    public function test_member_key_strips_leading_zeros_and_is_null_without_a_member(): void
    {
        $this->assertSame('100100129', MemberKey::from('0100100129'));
        $this->assertSame('100100129', MemberKey::from('100100129'));
        $this->assertSame('5', MemberKey::from('0005'));
        $this->assertNull(MemberKey::from(null));
        $this->assertNull(MemberKey::from(''));
        $this->assertNull(MemberKey::from('000'));
    }

    public function test_internal_election_refuses_a_second_vote_by_the_same_member(): void
    {
        $now = now();
        $e1 = DB::table('internal_elections')->insertGetId(['title' => 'T e1', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $e2 = DB::table('internal_elections')->insertGetId(['title' => 'T e2', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $c1 = DB::table('internal_election_candidates')->insertGetId(['first_name' => 'T', 'father_name' => 'C', 'family_name' => 'One', 'election_id' => $e1]);
        $c2 = DB::table('internal_election_candidates')->insertGetId(['first_name' => 'T', 'father_name' => 'C', 'family_name' => 'Two', 'election_id' => $e2]);

        $a = $this->makeAppUser('T acct A', '5570000001');
        $b = $this->makeAppUser('T acct B (zero-padded)', '05570000001');
        $other = $this->makeAppUser('T other member', '5570000002');
        $row = fn ($user, $cand, $election, $key) => ['user_id' => $user, 'candidate_id' => $cand, 'rank' => 1, 'internal_election_id' => $election, 'weight' => 1, 'voted_at' => $now, 'member_key' => $key];

        DB::table('internal_election_votes')->insert($row($a, $c1, $e1, MemberKey::from('5570000001')));

        // the second account of the same member, same election: refused by the database
        try {
            DB::table('internal_election_votes')->insert($row($b, $c1, $e1, MemberKey::from('05570000001')));
            $this->fail('the database should have refused the second vote of the same member');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->getCode());
        }

        // a different member, and the same member in ANOTHER election, are fine
        DB::table('internal_election_votes')->insert($row($other, $c1, $e1, MemberKey::from('5570000002')));
        DB::table('internal_election_votes')->insert($row($b, $c2, $e2, MemberKey::from('05570000001')));
        $this->assertSame(3, DB::table('internal_election_votes')->whereIn('internal_election_id', [$e1, $e2])->count());

        // rows without a member key (old rows / deleted accounts) never collide with each other
        DB::table('internal_election_votes')->where('user_id', $other)->update(['member_key' => null]);
        DB::table('internal_election_votes')->where('user_id', $a)->update(['member_key' => null]);
        $this->assertSame(2, DB::table('internal_election_votes')->where('internal_election_id', $e1)->whereNull('member_key')->count());
    }

    public function test_regular_poll_refuses_a_second_answer_by_the_same_member_even_past_the_code_check(): void
    {
        $now = now();
        $pollId = DB::table('polls')->insertGetId([
            'question' => json_encode(['ar' => 'T rule poll', 'en' => 'T rule poll']), 'show' => 1, 'show_for_guest' => 0,
            'expiry_date' => now()->addDay(), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $o1 = DB::table('polls_options')->insertGetId(['poll_id' => $pollId, 'option' => json_encode(['ar' => 'A', 'en' => 'A']), 'created_at' => $now, 'updated_at' => $now]);

        $a = $this->makeAppUser('T acct A', '5580000001');
        $b = $this->makeAppUser('T acct B (zero-padded)', '05580000001');

        $repo = app(ApiRepository::class);
        $repo->userAnswerPoll(AppUser::find($a), $o1);
        $this->assertSame('5580000001', DB::table('users_polls')->where('app_user_id', $a)->value('member_key'));

        // the repository call bypasses the code-level "already answered" check: the database must stop it
        try {
            $repo->userAnswerPoll(AppUser::find($b), $o1);
            $this->fail('the database should have refused the second answer of the same member');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->getCode());
        }
        $this->assertSame(1, DB::table('users_polls')->where('poll_id', $pollId)->count());
    }
}
