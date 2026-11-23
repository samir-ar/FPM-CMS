<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// Found by the member-to-member / guest check of 2026-11-23: opening ONE news item or event by id answered for ANY id, while the
// lists hide what is not for you. Now the by-id calls (open, like, share) follow the same rule as the lists:
//   visible = linked to one of your groups or to the two "everyone" groups (81 / 82); a guest additionally needs the "Public" tick.
class ItemVisibilityTest extends CouncilTestCase
{
    private const TOKEN = 'zq-visibility-token';

    private int $privateGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->privateGroup = (int) (DB::table('groups')->whereNotIn('group_id', [81, 82])->value('group_id') ?: 999001);

        // a member that is in NO group: he sees only what is linked to the "everyone" groups
        DB::table('fpm_users')->insert([
            'MemberId' => 5900000444, 'UserFullName' => 'ZQ visibility', 'MobileNumber' => '5900000444', 'Email' => '', 'district' => '', 'LastUnitPosition' => '',
            'member_status' => 1, 'BirthDate' => '1970-01-01', 'CanCreateEventRequest' => 0, 'CanCreateMeetings' => 0, 'ElectionState' => '', 'ElectionStateId' => 0,
            'ElectionStateVoltersNumber' => 0, 'Role' => '', 'RoleId' => 0, 'PersonImage' => '', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->makeAppUser('ZQ visibility', '5900000444', ['token' => self::TOKEN, 'verified' => 1]);
    }

    private function news(int $group, bool $public): int
    {
        $id = DB::table('news')->insertGetId([
            'title' => json_encode(['en' => 'ZQ', 'ar' => 'ZQ خبر'], JSON_UNESCAPED_UNICODE), 'source' => json_encode(['en' => 's', 'ar' => 'م'], JSON_UNESCAPED_UNICODE),
            'details' => json_encode(['en' => 'd', 'ar' => 'ت'], JSON_UNESCAPED_UNICODE), 'date' => now(), 'show_for_guest' => $public ? 1 : 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('groups_news')->insert(['news_id' => $id, 'group_group_id' => $group, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function event(int $group, bool $public): int
    {
        $id = DB::table('events')->insertGetId([
            'name' => json_encode(['en' => 'ZQ', 'ar' => 'ZQ نشاط'], JSON_UNESCAPED_UNICODE), 'organized_by' => json_encode(['en' => 'o', 'ar' => 'م'], JSON_UNESCAPED_UNICODE),
            'location' => json_encode(['en' => 'l', 'ar' => 'م'], JSON_UNESCAPED_UNICODE), 'details' => json_encode(['en' => 'd', 'ar' => 'ت'], JSON_UNESCAPED_UNICODE),
            'lng' => 0, 'lat' => 0, 'from_date' => now()->addDay(), 'to_date' => now()->addDays(2), 'show_for_guest' => $public ? 1 : 0, 'image' => '', 'public' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('groups_events')->insert(['event_id' => $id, 'group_group_id' => $group, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function asMember(): array
    {
        return ['token' => self::TOKEN];
    }

    public function test_a_guest_opens_only_an_event_ticked_public_and_for_everyone(): void
    {
        $visible = $this->event(81, true);
        $notPublic = $this->event(81, false);       // for everyone, but the admin did NOT show it to guests
        $otherGroup = $this->event($this->privateGroup, true); // ticked public but only for a private group

        $this->postJson('/api/v2/single-event', ['event_id' => $visible])->assertOk()->assertJsonPath('id', $visible);
        $this->postJson('/api/v2/single-event', ['event_id' => $notPublic])->assertStatus(404);
        $this->postJson('/api/v2/single-event', ['event_id' => $otherGroup])->assertStatus(404);
    }

    public function test_a_member_opens_news_and_events_of_the_everyone_groups_but_not_of_other_groups(): void
    {
        $newsOpen = $this->news(81, false);
        $newsClosed = $this->news($this->privateGroup, false);
        $eventOpen = $this->event(82, false);
        $eventClosed = $this->event($this->privateGroup, false);

        $this->postJson('/api/v2/single-news', ['news_id' => $newsOpen], $this->asMember())->assertOk();
        $this->postJson('/api/v2/single-news', ['news_id' => $newsClosed], $this->asMember())->assertStatus(404);

        $this->postJson('/api/v2/single-event', ['event_id' => $eventOpen], $this->asMember())->assertOk();
        $this->postJson('/api/v2/single-event', ['event_id' => $eventClosed], $this->asMember())->assertStatus(404);
    }

    public function test_like_and_share_follow_the_same_rule(): void
    {
        $newsOpen = $this->news(81, false);
        $newsClosed = $this->news($this->privateGroup, false);
        $eventOpen = $this->event(81, false);
        $eventClosed = $this->event($this->privateGroup, false);

        $this->postJson('/api/v2/news-like', ['news_id' => $newsOpen, 'like' => 1], $this->asMember())->assertOk();
        $this->postJson('/api/v2/news-share', ['news_id' => $newsOpen], $this->asMember())->assertOk();
        $this->postJson('/api/v2/event-like', ['event_id' => $eventOpen, 'like' => 1], $this->asMember())->assertOk();
        $this->postJson('/api/v2/event-share', ['event_id' => $eventOpen], $this->asMember())->assertOk();

        foreach ([['news-like', ['news_id' => $newsClosed, 'like' => 1]], ['news-share', ['news_id' => $newsClosed]],
                  ['event-like', ['event_id' => $eventClosed, 'like' => 1]], ['event-share', ['event_id' => $eventClosed]]] as [$route, $data]) {
            $this->postJson('/api/v2/' . $route, $data, $this->asMember())->assertStatus(404);
        }
        // nothing was counted on the closed ones
        $this->assertSame(0, (int) DB::table('news')->where('id', $newsClosed)->value('shares'));
        $this->assertSame(0, DB::table('users_news')->where('news_id', $newsClosed)->count());
    }
}
