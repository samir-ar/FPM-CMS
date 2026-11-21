<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Repositories\V2\ApiRepository;
use App\V2\HomeCardIcon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// The round icon on a Home card is chosen per card TYPE in the CMS page "Home card icons". A news item's own logo is kept
// unless "always use this icon" is ticked; events and polls take the type icon; with none set the old behaviour stays.
class HomeCardIconsTest extends CouncilTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.disks.s3.url' => 'https://cdn.example.com']);
        Storage::forgetDisk('s3');
        HomeCardIcon::forget();
    }

    private function icon(string $type, ?string $newsSourceImage = null, ?string $eventSourceImage = null): ?string
    {
        HomeCardIcon::forget();
        $m = new \ReflectionMethod(ApiRepository::class, 'homeCardIcon');
        $m->setAccessible(true);
        $g = (object) ['source_image' => $type === 'NEWS' ? $newsSourceImage : $eventSourceImage];

        return $m->invoke(app(ApiRepository::class), $g, $type);
    }

    public function test_with_no_icon_set_nothing_changes(): void
    {
        $this->assertStringEndsWith('/images/news/logo.png', $this->icon('NEWS', 'logo.png'));
        $this->assertNull($this->icon('NEWS', null));
        $this->assertNull($this->icon('POLL'));
        $this->assertNull($this->icon('EVENT'));
    }

    public function test_type_icons_apply_and_a_news_own_logo_is_kept_unless_forced(): void
    {
        HomeCardIcon::updateOrCreate(['type' => 'POLL'], ['image' => 'poll.png']);
        HomeCardIcon::updateOrCreate(['type' => 'EVENT'], ['image' => 'ev ent.png']);
        HomeCardIcon::updateOrCreate(['type' => 'NEWS'], ['image' => 'news.png', 'use_for_all' => false]);

        $this->assertStringEndsWith('/images/home_icons/poll.png', $this->icon('POLL'));
        $this->assertStringEndsWith('/images/home_icons/ev%20ent.png', $this->icon('EVENT'), 'the file name is encoded');
        $this->assertStringEndsWith('/images/news/own.png', $this->icon('NEWS', 'own.png'), 'its own logo wins when not forced');
        $this->assertStringEndsWith('/images/home_icons/news.png', $this->icon('NEWS', null), 'no own logo: the type icon');

        HomeCardIcon::where('type', 'NEWS')->update(['use_for_all' => true]);
        $this->assertStringEndsWith('/images/home_icons/news.png', $this->icon('NEWS', 'own.png'), 'forced: always the type icon');
    }

    public function test_the_page_saves_an_uploaded_icon_and_removes_it(): void
    {
        Storage::fake('s3');
        $admin = $this->adminUser();

        $this->actingAs($admin, 'admin')->get(route('admin.home-card-icons.index', [], false))->assertOk()->assertSee('Home card icons');

        $this->actingAs($admin, 'admin')->post(route('admin.home-card-icons.update', [], false), [
            'icon' => ['POLL' => UploadedFile::fake()->image('p.png', 200, 200)],
            'use_for_all_news' => '1',
        ])->assertRedirect();

        $this->assertNotNull(DB::table('home_card_icons')->where('type', 'POLL')->value('image'));
        $this->assertEquals(1, DB::table('home_card_icons')->where('type', 'NEWS')->value('use_for_all'));

        $this->actingAs($admin, 'admin')->post(route('admin.home-card-icons.update', [], false), ['remove' => ['POLL' => '1']])->assertRedirect();
        $this->assertNull(DB::table('home_card_icons')->where('type', 'POLL')->value('image'));
        $this->assertEquals(0, DB::table('home_card_icons')->where('type', 'NEWS')->value('use_for_all'), 'an unticked box turns it off');
    }
}
