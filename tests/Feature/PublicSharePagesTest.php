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
            'public' => $public ? 1 : 0,
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
            'public' => 1, 'created_at' => now(), 'updated_at' => now(),
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
}
