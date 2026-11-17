<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V2\ApiController;
use App\V2\AppUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Feature\NationalCouncil\CouncilTestCase;

/** A member replacing the photo shown in the app (Settings > Additional information). */
class ProfilePhotoTest extends CouncilTestCase
{
    private function callApi(int $userId, string $method, array $data): array
    {
        $r = Request::create('/x', 'POST', $data);
        $r->merge(['user' => AppUser::find($userId)]);
        $res = app(ApiController::class)->$method($r);

        return [$res->getStatusCode(), json_decode($res->getContent(), true)];
    }

    private function pngBase64(int $w, int $h, bool $transparent = false): string
    {
        $im = imagecreatetruecolor($w, $h);
        if ($transparent) {
            imagesavealpha($im, true);
            imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        } else {
            imagefill($im, 0, 0, imagecolorallocate($im, 200, 40, 40));
        }
        ob_start();
        imagepng($im);

        return base64_encode(ob_get_clean());
    }

    private function member(): int
    {
        return $this->makeAppUser('T Photo', '99900000081', ['image' => base64_encode('PARTY-PHOTO')]);
    }

    public function test_an_uploaded_photo_is_stored_as_a_small_plain_jpeg_in_its_own_column()
    {
        $me = $this->member();

        [$code, $body] = $this->callApi($me, 'uploadProfilePhoto', ['image' => $this->pngBase64(1600, 900)]);

        $this->assertSame(200, $code);
        $stored = DB::table('app_users')->where('id', $me)->value('custom_image');
        $this->assertSame($stored, $body['image']);

        $info = getimagesizefromstring(base64_decode($stored));
        $this->assertSame(IMAGETYPE_JPEG, $info[2], 're-encoded as JPEG');
        $this->assertSame(512, max($info[0], $info[1]), 'long side shrunk to 512');
        $this->assertLessThanOrEqual(300000, strlen(base64_decode($stored)));
    }

    public function test_the_partys_own_photo_column_is_not_touched()
    {
        $me = $this->member();

        $this->callApi($me, 'uploadProfilePhoto', ['image' => $this->pngBase64(300, 300)]);

        $this->assertSame(base64_encode('PARTY-PHOTO'), DB::table('app_users')->where('id', $me)->value('image'));
    }

    public function test_a_transparent_png_gets_a_white_background()
    {
        $me = $this->member();

        $this->callApi($me, 'uploadProfilePhoto', ['image' => $this->pngBase64(100, 100, true)]);

        $im = imagecreatefromstring(base64_decode(DB::table('app_users')->where('id', $me)->value('custom_image')));
        $rgb = imagecolorat($im, 5, 5);
        $this->assertGreaterThan(240, ($rgb >> 16) & 255);
        $this->assertGreaterThan(240, ($rgb >> 8) & 255);
        $this->assertGreaterThan(240, $rgb & 255);
    }

    public function test_things_that_are_not_images_are_refused_and_nothing_is_stored()
    {
        $me = $this->member();

        foreach ([
            '!!!not base64!!!',
            base64_encode(str_repeat('this is just text, not a picture. ', 20)),
            base64_encode('<?php echo 1; ?>' . str_repeat('x', 200)),
        ] as $bad) {
            [$code] = $this->callApi($me, 'uploadProfilePhoto', ['image' => $bad]);
            $this->assertSame(400, $code);
        }

        $this->assertNull(DB::table('app_users')->where('id', $me)->value('custom_image'));
    }

    public function test_removing_goes_back_to_the_partys_photo()
    {
        $me = $this->member();
        $this->callApi($me, 'uploadProfilePhoto', ['image' => $this->pngBase64(200, 200)]);

        [, $body] = $this->callApi($me, 'removeProfilePhoto', []);

        $this->assertNull(DB::table('app_users')->where('id', $me)->value('custom_image'));
        $this->assertSame(base64_encode('PARTY-PHOTO'), $body['image']);
    }

    public function test_the_custom_photo_is_never_serialised_as_a_second_field()
    {
        $me = $this->member();
        $this->callApi($me, 'uploadProfilePhoto', ['image' => $this->pngBase64(200, 200)]);

        $this->assertArrayNotHasKey('custom_image', AppUser::find($me)->toArray());
    }
}
