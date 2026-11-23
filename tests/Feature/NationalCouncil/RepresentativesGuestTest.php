<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// Only the Founder (id 11) and the President (id 12) boxes of the Representatives list are public.
// The national council, political council, coordinators ... are for logged-in members only.
class RepresentativesGuestTest extends CouncilTestCase
{
    private function ids(array $res): array
    {
        return collect($res['sections'] ?? [])->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all();
    }

    public function test_a_guest_gets_only_the_founder_and_the_president_boxes(): void
    {
        $this->assertGreaterThan(2, DB::table('dynamic_representatives')->count(), 'the test needs more than the two public boxes');

        $res = $this->postJson('/api/v2/representatives', [])->assertOk()->json();

        $this->assertSame(array_values(array_intersect([11, 12], DB::table('dynamic_representatives')->pluck('id')->all())), $this->ids($res));
        foreach ($res['sections'] as $s) {
            $this->assertContains((int) $s['id'], [11, 12]);
        }
    }

    public function test_a_logged_in_member_gets_the_whole_list(): void
    {
        $uid = $this->makeAppUser('ZQ member', '5900000111', ['token' => 'zq-reps-token-1', 'verified' => 1]);
        $this->assertNotNull($uid);

        $res = $this->withHeaders(['token' => 'zq-reps-token-1'])->postJson('/api/v2/representatives', [])->assertOk()->json();

        $all = DB::table('dynamic_representatives')->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all();
        $this->assertSame($all, $this->ids($res));
        $this->assertGreaterThan(2, count($res['sections']));
    }

    public function test_a_wrong_token_does_not_open_the_list(): void
    {
        $res = $this->withHeaders(['token' => 'not-a-real-token'])->postJson('/api/v2/representatives', [])->json();

        // an invalid token is refused (same answer as every other guest-or-member route), never the full list
        $this->assertArrayNotHasKey('sections', $res ?? []);
    }
}
