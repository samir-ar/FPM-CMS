<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Base for the National Council tests.
 *
 * The project has no separate test database, so these run against whatever
 * database .env points at. Two safeguards: every test is wrapped in a
 * transaction that is rolled back (DatabaseTransactions - never
 * RefreshDatabase), and the whole thing is skipped unless the database host is
 * local.
 */
abstract class CouncilTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // .env's APP_URL carries a folder prefix on this machine (http://localhost/CMS/public),
        // which would turn every $this->get('/admin/...') into a 404.
        \Illuminate\Support\Facades\URL::forceRootUrl('http://localhost');

        $host = config('database.connections.' . config('database.default') . '.host');
        if (!in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $this->markTestSkipped('National Council tests only run against a local database (host is ' . $host . ').');
        }
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\URL::forceRootUrl(null);

        parent::tearDown();
    }

    protected function adminUser(): \App\User
    {
        return \App\User::firstOrFail();
    }

    // Some real members from fpm_users to use as roster/voter ids.
    protected function sampleMemberIds(int $n): array
    {
        $ids = DB::table('fpm_users')->orderBy('id')->limit($n)->pluck('MemberId')->map(fn($i) => (string) $i)->all();

        if (count($ids) < $n) {
            $this->markTestSkipped('Needs at least ' . $n . ' rows in fpm_users.');
        }

        return $ids;
    }

    protected function makeAppUser(string $name, ?string $memberId, array $extra = []): int
    {
        return DB::table('app_users')->insertGetId($extra + [
            'name' => $name,
            'member_id' => $memberId,
            'phone_number' => (string) random_int(1000000000, 9999999999),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
