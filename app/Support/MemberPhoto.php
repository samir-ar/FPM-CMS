<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

// Party photos (fpm_users.PersonImage) that were cut off at 65,535 bytes, the column's old limit. A web browser draws whatever
// part there is, but the app refuses an incomplete picture file and shows nothing. This makes a complete copy to SEND; the
// stored bytes are never changed, and a photo that arrives whole again is sent exactly as it is.
class MemberPhoto
{
    const CUT_LENGTH = 65535;

    public static function isCut(string $bytes): bool
    {
        return strlen($bytes) === self::CUT_LENGTH
            && strncmp($bytes, "\xFF\xD8", 2) === 0
            && substr($bytes, -2) !== "\xFF\xD9";
    }

    // The bytes to send: a repaired copy for a cut photo, otherwise (or if it cannot be repaired) the bytes as given.
    public static function servable(string $bytes): string
    {
        if (!self::isCut($bytes)) {
            return $bytes;
        }

        // kept as text so that any cache driver can hold it
        return base64_decode(Cache::remember('member_photo_repaired:' . md5($bytes), 86400, function () use ($bytes) {
            return base64_encode(self::repair($bytes) ?? $bytes);
        }));
    }

    public static function repair(string $bytes): ?string
    {
        $info = @getimagesizefromstring($bytes);
        if (!$info || $info[0] * $info[1] > 25000000) {
            return null;
        }

        // the JPEG reader accepts the cut file and fills what is missing with flat grey
        $image = @imagecreatefromstring($bytes);
        if (!$image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // trim the grey strip at the bottom, unless so much is missing that only a sliver would be left
        $keep = $height;
        while ($keep > 0 && self::rowIsMissing($image, $keep - 1, $width)) {
            $keep--;
        }
        if ($keep < $height && $keep >= (int) ceil($height * 0.4)) {
            $cropped = imagecrop($image, ['x' => 0, 'y' => 0, 'width' => $width, 'height' => $keep]);
            if ($cropped) {
                imagedestroy($image);
                $image = $cropped;
            }
        }

        // saving again drops the camera's "turn me" flag, so turn the picture itself
        $angle = [3 => 180, 6 => -90, 8 => 90][self::orientation($bytes)] ?? 0;
        if ($angle && ($turned = imagerotate($image, $angle, 0))) {
            imagedestroy($image);
            $image = $turned;
        }

        ob_start();
        imagejpeg($image, null, 90);
        $out = ob_get_clean();
        imagedestroy($image);

        return $out ?: null;
    }

    // A row that never arrived is flat mid-grey up to the right edge (pictures are filled left to right, top to bottom).
    private static function rowIsMissing($image, int $y, int $width): bool
    {
        for ($x = max(0, $width - 8); $x < $width; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            foreach ([($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255] as $channel) {
                if (abs($channel - 128) > 2) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function orientation(string $bytes): int
    {
        if (!function_exists('exif_read_data')) {
            return 1;
        }

        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, $bytes);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        return (int) ($exif['Orientation'] ?? 1);
    }
}
