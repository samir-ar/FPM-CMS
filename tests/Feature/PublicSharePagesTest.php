<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Feature\NationalCouncil\CouncilTestCase;

/**
 * The web pages a shared news / event link lands on when the app is NOT
 * installed (when it is, the OS opens the app from the verified link instead).
 */
class PublicSharePagesTest extends CouncilTestCase
{
    private function makeEvent(bool $public, ?string $image = 'evt.jpg'): int
    {
        return DB::table('events')->insertGetId([
            // stored as JSON per language, like the real data
            'name' => json_encode(['ar' => 'حفل الاستقلال', 'en' => 'Independence party'], JSON_UNESCAPED_UNICODE),
            'details' => json_encode(['ar' => '<p>تفاصيل المناسبة الكاملة</p>'], JSON_UNESCAPED_UNICODE),
            'location' => 'بيروت',
            'organized_by' => 'التيار',
            'from_date' => now()->addDays(3),
            'to_date' => now()->addDays(4),
            'show_for_guest' => $public ? 1 : 0,
            'image' => $image,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_public_event_link_shows_the_event_and_a_preview_image_and_the_store_buttons()
    {
        $id = $this->makeEvent(true);

        $res = $this->get('/events/' . $id)->assertOk();
        $html = $res->getContent();

        $this->assertStringContainsString('حفل الاستقلال', $html);
        $this->assertStringContainsString('تفاصيل المناسبة الكاملة', $html);
        $this->assertStringContainsString('property="og:image"', $html, 'chat apps show the picture from this tag');
        $this->assertStringContainsString('evt.jpg', $html);
        $this->assertStringContainsString('id="get-app"', $html);
        $this->assertStringContainsString('play.google.com/store/apps/details?id=com.microbits.fpm', $html);
        $this->assertStringContainsString('apps.apple.com/app/id1083351430', $html);
        // the single button goes to the right store for the phone
        $this->assertStringContainsString('iPhone|iPad|iPod', $html);
    }

    public function test_a_member_only_event_never_leaks_its_content_to_the_web()
    {
        $id = $this->makeEvent(false);

        $res = $this->get('/events/' . $id)->assertOk();
        $html = $res->getContent();

        $this->assertStringNotContainsString('حفل الاستقلال', $html);
        $this->assertStringNotContainsString('تفاصيل المناسبة الكاملة', $html);
        $this->assertStringNotContainsString('property="og:image"', $html);
        $this->assertStringContainsString('id="get-app"', $html, 'but it still sends them to download the app');
    }

    public function test_an_event_with_text_in_only_one_language_still_shows_it()
    {
        $id = DB::table('events')->insertGetId([
            'name' => json_encode(['en' => 'English only name']),
            'details' => json_encode(['en' => '<p>English only details</p>']),
            'from_date' => now()->addDay(), 'to_date' => now()->addDays(2),
            'show_for_guest' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $html = $this->get('/events/' . $id)->assertOk()->getContent();

        $this->assertStringContainsString('English only name', $html);
        $this->assertStringContainsString('English only details', $html);
    }

    public function test_an_unknown_event_is_a_404()
    {
        $this->get('/events/999999999')->assertNotFound();
    }

    public function test_the_news_page_has_the_same_store_block()
    {
        $newsId = DB::table('news')->whereNull('deleted_at')->value('id');
        if (!$newsId) {
            $this->markTestSkipped('needs a news row');
        }

        $html = $this->get('/news/' . $newsId)->assertOk()->getContent();

        $this->assertStringContainsString('id="get-app"', $html);
        $this->assertStringContainsString('apps.apple.com/app/id1083351430', $html);
    }

    public function test_ios_universal_links_cover_news_and_events_and_android_links_are_still_served()
    {
        $aasa = $this->get('/.well-known/apple-app-site-association')->assertOk()->json();
        $paths = $aasa['applinks']['details'][0]['paths'];
        $this->assertContains('/news/*', $paths);
        $this->assertContains('/events/*', $paths);

        $this->get('/.well-known/assetlinks.json')->assertOk();
    }

    // ---- the tick in the event form: members only <-> public ----

    private function adminPut(int $id, array $data)
    {
        $payload = $data + [
            'name' => 'T name', 'name_ar' => 'اسم', 'organized_by' => 'T', 'strict_lang' => 'ar',
            'details' => 'd', 'details_ar' => 'تفاصيل',
            'from_date' => now()->addDay()->format('Y-m-d H:i'), 'to_date' => now()->addDays(2)->format('Y-m-d H:i'),
            'groups' => [81],
        ];

        return $this->actingAs($this->adminUser(), 'admin')->put(route('admin.events.update', $id, false), $payload);
    }

    public function test_the_event_form_has_the_public_tick_and_it_saves_and_clears_the_flag()
    {
        $id = $this->makeEvent(false);
        $admin = $this->adminUser();

        $html = $this->actingAs($admin, 'admin')->get(route('admin.events.edit', $id, false))->assertOk()->getContent();
        $this->assertStringContainsString('name="show_for_guest"', $html, 'the tick is on the form');
        $this->assertStringNotContainsString('name="show_for_guest" class="minimal" checked', $html, 'unticked for a members-only event');

        $this->adminPut($id, ['show_for_guest' => '1']);
        $this->assertSame(1, (int) DB::table('events')->where('id', $id)->value('show_for_guest'), 'ticking makes it public');

        $html = $this->actingAs($admin, 'admin')->get(route('admin.events.edit', $id, false))->getContent();
        $this->assertStringContainsString('name="show_for_guest" class="minimal" checked', $html, 'the form shows it ticked');

        $this->adminPut($id, []);
        $this->assertSame(0, (int) DB::table('events')->where('id', $id)->value('show_for_guest'), 'unticking makes it members only again');
    }

    public function test_the_tick_is_what_makes_the_web_page_show_the_event()
    {
        $id = $this->makeEvent(false);
        $this->assertStringNotContainsString('حفل الاستقلال', $this->get('/events/' . $id)->getContent());

        $this->adminPut($id, ['show_for_guest' => '1', 'name' => 'T name', 'name_ar' => 'حفل الاستقلال']);

        $this->assertStringContainsString('حفل الاستقلال', $this->get('/events/' . $id)->getContent());
    }

    public function test_the_tick_is_also_what_lets_a_guest_see_the_event_in_the_app()
    {
        $public = $this->makeEvent(true);
        $private = $this->makeEvent(false);
        foreach ([$public, $private] as $eventId) {
            DB::table('groups_events')->insert(['event_id' => $eventId, 'group_group_id' => 81, 'created_at' => now(), 'updated_at' => now()]);
        }

        $repo = app(\App\Http\Repositories\V2\ApiRepository::class);
        $guestSees = $repo->getEventsUpcomingByGroup([], true)->get()->pluck('id')->all();
        $memberSees = $repo->getEventsUpcomingByGroup([], false)->get()->pluck('id')->all();

        $this->assertContains($public, $guestSees);
        $this->assertNotContains($private, $guestSees, 'a members-only event is hidden from guests');
        $this->assertContains($private, $memberSees, 'but members still see it');
    }

    public function test_the_guest_home_feed_only_shows_public_events_but_members_still_see_all()
    {
        $public = $this->makeEvent(true);
        $private = $this->makeEvent(false);
        foreach ([$public, $private] as $eventId) {
            DB::table('groups_events')->insert(['event_id' => $eventId, 'group_group_id' => 81, 'created_at' => now(), 'updated_at' => now()]);
        }
        $ctrl = app(\App\Http\Controllers\Api\V2\ApiController::class);
        $repo = app(\App\Http\Repositories\V2\ApiRepository::class);

        // a guest: no logged-in user
        $guest = json_decode($ctrl->getWallFeed(\Illuminate\Http\Request::create('/x', 'POST', ['page' => 1]), $repo)->getContent(), true);
        $guestEvents = collect($guest['data'])->where('item_type', 'EVENT')->pluck('id')->all();
        $this->assertContains($public, $guestEvents);
        $this->assertNotContains($private, $guestEvents, 'a members-only event must not reach the guest Home feed');

        // a member
        $user = \App\V2\AppUser::find($this->makeAppUser('T member', '99900000091'));
        $req = \Illuminate\Http\Request::create('/x', 'POST', ['page' => 1]);
        $req->merge(['user' => $user]);
        $member = json_decode($ctrl->getWallFeed($req, $repo)->getContent(), true);
        $memberEvents = collect($member['data'])->where('item_type', 'EVENT')->pluck('id')->all();
        $this->assertContains($private, $memberEvents, 'members still see it');
    }
}
