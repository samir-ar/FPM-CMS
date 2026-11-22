<?php

namespace App\Support;

// How a stored file NAME goes into a picture link.
//
// The two servers build the link differently:
//   - with a CDN address in AWS_URL (staging): the name is simply appended, so the name must be URL-encoded by us (a "+" or a
//     "%" in it would otherwise be misread by the CDN: 403);
//   - without it (production): Amazon's S3 library builds the link and ALREADY encodes the path. Encoding the name first
//     would encode it twice ("+" -> "%252B") and the picture would not load.
// So: encode only when the CDN address is set.
class StorageUrl
{
    public static function fileName(?string $name): string
    {
        $name = (string) $name;

        return config('filesystems.disks.s3.url') ? rawurlencode($name) : $name;
    }
}
