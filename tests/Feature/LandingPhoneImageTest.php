<?php

namespace Tests\Feature;

use App\V2\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\NationalCouncil\CouncilTestCase;

// The phone picture on the public landing page (fpmmobile.com) is changeable from the CMS (App Versions page).
class LandingPhoneImageTest extends CouncilTestCase
{
    private $before;
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();
        // NEVER let a test upload to the real bucket (the local .env has FORCE_S3_STORAGE=true): the s3 disk is a fake one
        Storage::fake('s3');
        $this->before = Setting::query()->value('landing_phone_image');
    }

    protected function tearDown(): void
    {
        Setting::query()->update(['landing_phone_image' => $this->before]);
        foreach ($this->created as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_the_landing_page_shows_the_built_in_picture_until_one_is_uploaded(): void
    {
        Setting::query()->update(['landing_phone_image' => null]);
        $this->get('/')->assertOk()->assertSee('landing/img/app.svg', false);

        Setting::query()->update(['landing_phone_image' => 'zq-phone.png']);
        $this->get('/')->assertOk()->assertSee('landing/zq-phone.png', false)->assertDontSee('landing/img/app.svg', false);
    }

    public function test_an_admin_can_upload_a_new_picture_and_go_back_to_the_original(): void
    {
        $s = Setting::first();
        $this->actingAs($this->adminUser(), 'admin');

        $payload = [
            'android_version' => $s->android_version ?: '1', 'ios_version' => $s->ios_version ?: '1',
            'update_title' => $s->update_title ?: 't', 'update_message' => $s->update_message ?: 'm',
        ];

        $this->put('/admin/app-versions/update', $payload + ['landing_phone_image' => UploadedFile::fake()->image('phone.png', 600, 1200)])
            ->assertSessionHasNoErrors();

        $name = Setting::query()->value('landing_phone_image');
        $this->assertNotEmpty($name, 'the uploaded picture is remembered');
        Storage::disk('s3')->assertExists(config('app.aws_bucket_project_name') . '/storage/landing/' . $name);
        config(['app.force_s3_storage' => true]);
        $this->get('/')->assertSee('landing/' . $name, false);

        // not a picture: refused, the current one stays
        $this->put('/admin/app-versions/update', $payload + ['landing_phone_image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('landing_phone_image');
        $this->assertSame($name, Setting::query()->value('landing_phone_image'));

        // saving the form WITHOUT a new file keeps the picture
        $this->put('/admin/app-versions/update', $payload)->assertSessionHasNoErrors();
        $this->assertSame($name, Setting::query()->value('landing_phone_image'));

        // back to the original
        $this->put('/admin/app-versions/update', $payload + ['reset_landing_phone_image' => 'on'])->assertSessionHasNoErrors();
        $this->assertNull(Setting::query()->value('landing_phone_image'));
        $this->get('/')->assertSee('landing/img/app.svg', false);
    }

    public function test_the_cms_page_shows_the_upload_box(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        $this->get('/admin/app-versions')->assertOk()->assertSee('landing_phone_image', false)->assertSee('Landing page', false);
    }
}
