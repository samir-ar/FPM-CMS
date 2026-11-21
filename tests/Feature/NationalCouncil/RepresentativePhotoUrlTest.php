<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Controllers\Api\V2\ApiController;
use App\V2\Person;

// A photo file name with a "+" (e.g. "Gebran+Bassil.jpg") made the picture link return 403 from the CDN: the "+" must be
// sent as %2B. Names already stored encoded (%28) stay as they are.
class RepresentativePhotoUrlTest extends CouncilTestCase
{
    private function url(string $image): ?string
    {
        // same as staging/production: the disk has a CDN url and the file name is appended as it is
        config(['filesystems.disks.s3.url' => 'https://cdn.example.com']);
        \Storage::forgetDisk('s3');
        $m = new \ReflectionMethod(ApiController::class, 'representativePersonImageUrl');
        $m->setAccessible(true);

        return $m->invoke(app(ApiController::class), new Person(['image' => $image]), []);
    }

    public function test_a_plus_in_the_file_name_is_sent_as_percent_2b(): void
    {
        $this->assertStringEndsWith('/images/representatives/16360224311574838951Gebran%2BBassil.jpg', $this->url('16360224311574838951Gebran+Bassil.jpg'));
        $this->assertStringEndsWith('/images/representatives/1650356337gb-01-min.jpg', $this->url('1650356337gb-01-min.jpg'));
        $this->assertStringEndsWith('unnamed%2B%25281%2529.jpg', $this->url('unnamed+%281%29.jpg'));
    }
}
