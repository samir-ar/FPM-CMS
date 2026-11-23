<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V2\ApiController;
use App\Http\Repositories\V2\ApiRepository;
use App\V2\AppUser;
use App\V2\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Feature\NationalCouncil\CouncilTestCase;

/**
 * Likes/shares on EVENTS in the Home feed. The app used to send an event's id
 * to the news endpoints, so the like was attached to a news article with the
 * same numeric id (or rejected) and the feed never reported it back, so it
 * vanished on refresh.
 */
class EventLikesTest extends CouncilTestCase
{
    private function makeEvent(): int
    {
        $id = DB::table('events')->insertGetId([
            'name' => 'T event',
            'details' => '<p>details</p>',
            'location' => 'Beirut',
            'from_date' => now()->addDay(),
            'to_date' => now()->addDays(2),
            'public' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // like every real event it is for the "everyone" group (81): an event without any group is hidden everywhere, and
        // since 2026-11-23 liking / sharing / opening an event follows the same visibility as the lists
        DB::table('groups_events')->insert(['event_id' => $id, 'group_group_id' => 81, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function callApi(int $userId, string $method, array $data): array
    {
        $r = Request::create('/x', 'POST', $data);
        $r->merge(['user' => AppUser::find($userId)]);
        $res = app(ApiController::class)->$method($r, app(ApiRepository::class));

        return [$res->getStatusCode(), json_decode($res->getContent(), true)];
    }

    private function feedItem(int $eventId, int $userId): array
    {
        $repo = app(ApiRepository::class);
        $user = AppUser::find($userId);
        [$counts, $mine] = $repo->getEventLikesData(collect([$eventId]), $user);

        return $repo->getGenericInstance(Event::find($eventId), 'EVENT', null, $user, collect(), [], $counts, $mine)->toArray();
    }

    public function test_a_liked_event_is_reported_back_by_the_feed_so_it_survives_a_refresh()
    {
        $event = $this->makeEvent();
        $me = $this->makeAppUser('T Me', '99900000071');
        $other = $this->makeAppUser('T Other', '99900000072');

        $before = $this->feedItem($event, $me);
        $this->assertSame(0, $before['likes_nb']);
        $this->assertFalse($before['like']);

        [$code, $body] = $this->callApi($me, 'likeEvent', ['event_id' => $event, 'like' => 1]);
        $this->assertSame(200, $code);
        $this->assertSame(1, $body['likes_nb']);
        $this->assertTrue($body['like']);

        // "refresh the page": the feed is built again from the database
        $after = $this->feedItem($event, $me);
        $this->assertSame(1, $after['likes_nb']);
        $this->assertTrue($after['like'], 'my like is still there after a refresh');

        // somebody else's feed sees the count but not my like
        $theirs = $this->feedItem($event, $other);
        $this->assertSame(1, $theirs['likes_nb']);
        $this->assertFalse($theirs['like']);
    }

    public function test_unliking_removes_it_and_liking_twice_keeps_one_row()
    {
        $event = $this->makeEvent();
        $me = $this->makeAppUser('T Me', '99900000071');

        $this->callApi($me, 'likeEvent', ['event_id' => $event, 'like' => 1]);
        $this->callApi($me, 'likeEvent', ['event_id' => $event, 'like' => 1]);
        $this->assertSame(1, DB::table('event_likes')->where('event_id', $event)->count());

        [, $body] = $this->callApi($me, 'likeEvent', ['event_id' => $event, 'like' => 0]);
        $this->assertSame(0, $body['likes_nb']);
        $this->assertFalse($this->feedItem($event, $me)['like']);
    }

    public function test_liking_an_event_never_touches_news_likes()
    {
        $event = $this->makeEvent();
        $me = $this->makeAppUser('T Me', '99900000071');
        $newsLikesBefore = DB::table('users_news')->count();

        $this->callApi($me, 'likeEvent', ['event_id' => $event, 'like' => 1]);

        $this->assertSame($newsLikesBefore, DB::table('users_news')->count());
    }

    public function test_an_unknown_event_is_refused()
    {
        $me = $this->makeAppUser('T Me', '99900000071');

        [$code] = $this->callApi($me, 'likeEvent', ['event_id' => 999999999, 'like' => 1]);

        $this->assertSame(400, $code);
    }

    public function test_sharing_an_event_counts_on_the_event_and_not_on_news()
    {
        $event = $this->makeEvent();
        $me = $this->makeAppUser('T Me', '99900000071');
        $newsSharesBefore = (int) DB::table('news')->sum('shares');

        $this->callApi($me, 'shareEvent', ['event_id' => $event]);
        [, $body] = $this->callApi($me, 'shareEvent', ['event_id' => $event]);

        $this->assertSame(2, $body['shares']);
        $this->assertSame($newsSharesBefore, (int) DB::table('news')->sum('shares'));
        $this->assertSame(2, $this->feedItem($event, $me)['shares']);
    }

    public function test_news_likes_data_for_the_feed_is_unchanged()
    {
        $newsId = DB::table('news')->value('id');
        if (!$newsId) {
            $this->markTestSkipped('needs a news row');
        }
        $me = $this->makeAppUser('T Me', '99900000071');
        DB::table('users_news')->insert(['user_id' => $me, 'news_id' => $newsId, 'created_at' => now(), 'updated_at' => now()]);

        [$counts, $mine] = app(ApiRepository::class)->getNewsLikesData(collect([$newsId]), AppUser::find($me));

        $this->assertContains($newsId, $mine);
        $this->assertGreaterThanOrEqual(1, $counts[$newsId]);
    }
}
