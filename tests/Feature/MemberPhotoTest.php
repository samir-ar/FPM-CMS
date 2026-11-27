<?php

namespace Tests\Feature;

use App\Support\MemberPhoto;
use Tests\TestCase;

/** Party photos cut off at 65,535 bytes are sent to the app as complete pictures. No database is touched. */
class MemberPhotoTest extends TestCase
{
    // a picture of random dots, so that its file is far bigger than the old limit
    private function bigJpeg(int $w = 600, int $h = 800, bool $progressive = false, int $quality = 60): string
    {
        $im = imagecreatetruecolor($w, $h);
        mt_srand(7);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                imagesetpixel($im, $x, $y, mt_rand(0, 0xFFFFFF));
            }
        }
        imageinterlace($im, $progressive);
        ob_start();
        imagejpeg($im, null, $quality);

        return ob_get_clean();
    }

    // the same file with a camera "turn me" flag put in front
    private function withOrientation(string $jpeg, int $orientation): string
    {
        $tiff = 'II' . pack('vV', 42, 8) . pack('v', 1) . pack('vvVvv', 0x0112, 3, 1, $orientation, 0) . pack('V', 0);
        $body = "Exif\0\0" . $tiff;

        return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($body) + 2) . $body . substr($jpeg, 2);
    }

    private function cut(string $jpeg): string
    {
        $this->assertGreaterThan(MemberPhoto::CUT_LENGTH, strlen($jpeg), 'the test picture must be bigger than the limit');

        return substr($jpeg, 0, MemberPhoto::CUT_LENGTH);
    }

    public function test_a_whole_photo_is_sent_exactly_as_it_is()
    {
        $whole = $this->bigJpeg();

        $this->assertSame($whole, MemberPhoto::servable($whole));
        $this->assertSame('PARTY-PHOTO', MemberPhoto::servable('PARTY-PHOTO'));
    }

    private function bottomRightIsGrey(string $jpeg): bool
    {
        $im = imagecreatefromstring($jpeg);
        for ($x = imagesx($im) - 8; $x < imagesx($im); $x++) {
            $rgb = imagecolorat($im, $x, imagesy($im) - 1);
            foreach ([($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255] as $c) {
                if (abs($c - 128) > 2) {
                    return false;
                }
            }
        }

        return true;
    }

    public function test_a_cut_photo_becomes_a_complete_picture_without_the_grey_strip()
    {
        // about two thirds of this one arrives, like the real photos where only the bottom is lost
        $out = MemberPhoto::servable($this->cut($this->bigJpeg(300, 330, false, 90)));

        $this->assertSame("\xFF\xD9", substr($out, -2), 'the file has its end again');
        [$w, $h] = getimagesizefromstring($out);
        $this->assertSame(300, $w);
        $this->assertLessThan(330, $h, 'the missing part is trimmed');
        $this->assertGreaterThanOrEqual(132, $h);
        $this->assertFalse($this->bottomRightIsGrey($out));
    }

    public function test_when_most_is_missing_the_picture_keeps_its_size()
    {
        $out = MemberPhoto::servable($this->cut($this->bigJpeg()));

        $this->assertSame("\xFF\xD9", substr($out, -2));
        $this->assertSame([600, 800], array_slice(getimagesizefromstring($out), 0, 2), 'no thin sliver');
    }

    public function test_a_sideways_camera_photo_stays_the_right_way_up()
    {
        $out = MemberPhoto::servable($this->cut($this->withOrientation($this->bigJpeg(300, 330, false, 90), 6)));

        [$w, $h] = getimagesizefromstring($out);
        $this->assertSame(300, $h, 'turned by a quarter: the old width is now the height');
        $this->assertLessThan(330, $w);
    }

    public function test_a_cut_progressive_photo_is_also_completed()
    {
        $out = MemberPhoto::servable($this->cut($this->bigJpeg(600, 800, true)));

        $this->assertSame("\xFF\xD9", substr($out, -2));
        $this->assertSame(600, getimagesizefromstring($out)[0]);
    }

    public function test_a_file_that_cannot_be_repaired_is_sent_unchanged()
    {
        $junk = "\xFF\xD8" . str_repeat('x', MemberPhoto::CUT_LENGTH - 2);

        $this->assertSame($junk, MemberPhoto::servable($junk));
    }
}
