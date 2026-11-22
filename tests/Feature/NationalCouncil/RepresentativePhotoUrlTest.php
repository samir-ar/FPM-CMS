<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Controllers\Api\V2\ApiController;
use App\V2\Person;

// A photo file name with a "+" (e.g. "Gebran+Bassil.jpg") or a "%" must reach the picture server correctly encoded ONCE.
// Staging builds the link with a CDN address (the name is appended, so WE encode it); production builds it with Amazon's own
// S3 library (which already encodes it: encoding first made it "%252B" and the picture did not load).
class RepresentativePhotoUrlTest extends CouncilTestCase
{
    private function url(string $image, ?string $cdn): ?string
    {
        config(['filesystems.disks.s3.url' => $cdn]);
        \Storage::forgetDisk('s3');
        $m = new \ReflectionMethod(ApiController::class, 'representativePersonImageUrl');
        $m->setAccessible(true);

        return $m->invoke(app(ApiController::class), new Person(['image' => $image]), []);
    }

    public function test_with_a_cdn_address_the_name_is_encoded_once_by_us(): void
    {
        $cdn = 'https://cdn.example.com';
        $this->assertStringEndsWith('/images/representatives/16360224311574838951Gebran%2BBassil.jpg', $this->url('16360224311574838951Gebran+Bassil.jpg', $cdn));
        $this->assertStringEndsWith('/images/representatives/1650356337gb-01-min.jpg', $this->url('1650356337gb-01-min.jpg', $cdn));
        $this->assertStringEndsWith('unnamed%2B%25281%2529.jpg', $this->url('unnamed+%281%29.jpg', $cdn));
    }

    public function test_without_a_cdn_address_amazons_library_encodes_it_and_we_do_not_encode_twice(): void
    {
        $url = $this->url('16360224311574838951Gebran+Bassil.jpg', null);
        $this->assertStringContainsString('amazonaws.com', $url, 'the direct Amazon link (production)');
        $this->assertStringEndsWith('Gebran%2BBassil.jpg', $url);
        $this->assertStringNotContainsString('%252B', $url, 'a "+" must not be encoded twice');

        $this->assertStringEndsWith('unnamed%2B%25281%2529.jpg', $this->url('unnamed+%281%29.jpg', null));
    }

    public function test_both_ways_give_the_same_final_ending_for_the_same_file(): void
    {
        foreach (['Roger+Azar.jpg', 'WhatsApp+Image+2021-11-03+at+2.53.33+PM.jpeg', 'unnamed+%281%29+%281%29.jpg', 'plain-name.jpg'] as $name) {
            $a = basename(parse_url($this->url($name, 'https://cdn.example.com'), PHP_URL_PATH));
            $b = basename(parse_url($this->url($name, null), PHP_URL_PATH));
            $this->assertSame($a, $b, "same link ending for $name");
        }
    }
}
