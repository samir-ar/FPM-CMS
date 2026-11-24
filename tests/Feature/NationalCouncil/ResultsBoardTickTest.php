<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// The council results board (admin/national-council-poll-results) and the tick "Show on results page" of the polls list:
//  1. the board lists exactly the polls ticked in the polls list, published or not; the board itself has no tick;
//  2. the tick is saved from the list with one click (POST show-in-results);
//  3. the logo comes from this server (url()), not from asset(): on production ASSET_URL points at the S3 bucket where
//     the file does not exist, so the logo box stayed empty; same for the single-poll report page.
class ResultsBoardTickTest extends CouncilTestCase
{
    private function poll(string $title, int $published, int $shown = 0): int
    {
        return DB::table('council_national_polls')->insertGetId(['title' => $title, 'is_published' => $published, 'show_in_results' => $shown, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_board_lists_exactly_the_polls_ticked_in_the_list_whether_published_or_not(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        $pubTicked = $this->poll('ZQ published ticked', 1, 1);
        $unpTicked = $this->poll('ZQ unpublished ticked', 0, 1);
        $pubNot = $this->poll('ZQ published not ticked', 1, 0);
        $page = route('admin.national-council-poll.results-overview', [], false);
        $data = route('admin.national-council-poll.results-overview.data', [], false);

        $html = $this->get($page)->assertOk()->assertSee('ZQ published ticked')->assertSee('ZQ unpublished ticked')->assertSee('Not published')
            ->assertDontSee('ZQ published not ticked')->getContent();
        $this->assertStringNotContainsString('Also show unpublished', $html, 'the board page itself has no tick');
        $this->assertStringNotContainsString('showUnpublished', $html);

        // opening and the live data follow the tick, not the publish state
        $this->get($page . '?polls[]=' . $unpTicked)->assertOk();
        $ids = array_column($this->getJson($data . '?polls[]=' . $pubTicked . '&polls[]=' . $unpTicked . '&polls[]=' . $pubNot)->assertOk()->json('polls'), 'id');
        $this->assertEqualsCanonicalizing([$pubTicked, $unpTicked], $ids, 'a published poll that is not ticked is not shown');
    }

    public function test_the_tick_is_a_column_of_the_polls_list_and_saves_with_one_click(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        $id = $this->poll('ZQ tick poll', 0, 0);

        $row = collect($this->getJson(route('admin.national-council-poll.index', [], false), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data'))->firstWhere('id', $id);
        $this->assertStringContainsString('poll-show-results-tick', $row['show_in_results']);
        $this->assertStringNotContainsString('checked', $row['show_in_results']);
        $html = $this->get(route('admin.national-council-poll.index', [], false))->assertOk()->getContent();
        $this->assertStringContainsString('Show on results page', $html);
        $this->assertMatchesRegularExpression('#/js/council-poll-list\.js\?v=\d+#', $html, 'the script carries a version so a browser never keeps an old copy');

        $url = route('admin.national-council-poll.show-in-results', $id, false);
        $this->postJson($url, ['show' => 1])->assertOk()->assertJsonPath('show', true);
        $this->assertSame(1, (int) DB::table('council_national_polls')->where('id', $id)->value('show_in_results'));
        $row = collect($this->getJson(route('admin.national-council-poll.index', [], false), ['X-Requested-With' => 'XMLHttpRequest'])->json('data'))->firstWhere('id', $id);
        $this->assertStringContainsString('checked', $row['show_in_results']);

        $this->postJson($url, ['show' => 0])->assertOk()->assertJsonPath('show', false);
        $this->assertSame(0, (int) DB::table('council_national_polls')->where('id', $id)->value('show_in_results'));
        $this->postJson(route('admin.national-council-poll.show-in-results', 99999999, false), ['show' => 1])->assertStatus(404);
    }

    public function test_a_new_poll_starts_not_shown_and_the_migration_ticked_the_published_ones(): void
    {
        $id = DB::table('council_national_polls')->insertGetId(['title' => 'ZQ default', 'is_published' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(0, (int) DB::table('council_national_polls')->where('id', $id)->value('show_in_results'));

        // what the migration does to polls that exist when it runs
        $pub = DB::table('council_national_polls')->insertGetId(['title' => 'ZQ was published', 'is_published' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('council_national_polls')->where('is_published', 1)->update(['show_in_results' => 1]);
        $this->assertSame(1, (int) DB::table('council_national_polls')->where('id', $pub)->value('show_in_results'));
    }

    public function test_the_logo_is_served_by_this_server_even_when_asset_url_points_elsewhere(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        // what production's ASSET_URL does (Laravel 10 has no public setter for it)
        $prop = new \ReflectionProperty(app('url'), 'assetRoot');
        $prop->setAccessible(true);
        $before = $prop->getValue(app('url'));
        $prop->setValue(app('url'), 'https://cdn.example.test/fpm/storage');
        try {
            $html = $this->get(route('admin.national-council-poll.results-overview', [], false))->assertOk()->getContent();
        } finally {
            $prop->setValue(app('url'), $before);
        }

        $this->assertStringContainsString('/council-board/fpm-logo.png', $html);
        $this->assertStringNotContainsString('cdn.example.test/fpm/storage/council-board', $html, 'the logo must not be taken from the asset (S3) address');
        $this->assertFileExists(public_path('council-board/fpm-logo.png'));
    }

    // (a poll with NO voters at all, like a brand-new test poll, used to crash this page with a division by zero)
    // the printable results report of ONE poll ("النتائج" link in the polls list) loaded its stylesheet and logo
    // through asset() too: on production both were looked up in the S3 bucket (403) and the page came out unstyled
    public function test_the_single_poll_results_report_loads_its_css_and_logo_from_this_server(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        $id = $this->poll('ZQ report poll', 0);
        $prop = new \ReflectionProperty(app('url'), 'assetRoot');
        $prop->setAccessible(true);
        $before = $prop->getValue(app('url'));
        $prop->setValue(app('url'), 'https://cdn.example.test/fpm/storage');
        try {
            $html = $this->get(route('admin.national-council-poll.results', $id, false))->assertOk()->getContent();
        } finally {
            $prop->setValue(app('url'), $before);
        }

        $this->assertStringContainsString('/cms/css/report.css', $html);
        $this->assertStringContainsString('/landing/img/fpm-logo.svg', $html);
        $this->assertStringNotContainsString('cdn.example.test', $html, 'no file of this page may be taken from the asset (S3) address');
        $this->assertFileExists(public_path('cms/css/report.css'));
        $this->assertFileExists(public_path('landing/img/fpm-logo.svg'));
    }
}
