<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// The council results board (admin/national-council-poll-results):
//  1. a tick "Also show unpublished polls" lets the admin open the board with a test poll that is not published
//     (without the tick only published polls are listed, as before);
//  2. the logo comes from this server (url()), not from asset(): on production ASSET_URL points at the S3 bucket where
//     the file does not exist, so the logo box stayed empty.
class ResultsBoardUnpublishedTest extends CouncilTestCase
{
    private function poll(string $title, int $published): int
    {
        return DB::table('council_national_polls')->insertGetId(['title' => $title, 'is_published' => $published, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_unpublished_polls_are_listed_and_open_only_when_the_tick_is_on(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        $pub = $this->poll('ZQ published poll', 1);
        $unp = $this->poll('ZQ unpublished poll', 0);
        $page = route('admin.national-council-poll.results-overview', [], false);
        $data = route('admin.national-council-poll.results-overview.data', [], false);

        // tick OFF (default): as before
        $html = $this->get($page)->assertOk()->assertSee('ZQ published poll')->assertDontSee('ZQ unpublished poll')->getContent();
        $this->assertStringContainsString('id="showUnpublished"', $html);
        $this->assertDoesNotMatchRegularExpression('/id="showUnpublished"\s+checked/', $html);
        $this->assertSame([$pub], array_column($this->getJson($data . '?polls[]=' . $pub . '&polls[]=' . $unp)->assertOk()->json('polls'), 'id'));

        // tick ON: the unpublished poll is listed (marked "Not published"), opens, and the live data returns it
        $html = $this->get($page . '?unpublished=1')->assertOk()->assertSee('ZQ published poll')->assertSee('ZQ unpublished poll')->assertSee('Not published')->getContent();
        $this->assertMatchesRegularExpression('/id="showUnpublished"\s+checked/', $html);
        $this->get($page . '?unpublished=1&polls[]=' . $unp)->assertOk();
        $json = $this->getJson($data . '?unpublished=1&polls[]=' . $unp)->assertOk()->json('polls');
        $this->assertSame([$unp], array_column($json, 'id'));
        $this->assertFalse($json[0]['published']);
        $this->assertStringContainsString("unpublished: true", $this->get($page . '?unpublished=1')->getContent(), 'the page keeps asking for unpublished polls');
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
