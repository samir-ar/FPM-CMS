<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// CMS > App Users: Activity (opened / not opened since 24 Sept), the "since 24 Sept" tick box and the New mark.
class AppUsersOldNewFilterTest extends CouncilTestCase
{
    /** [new+active, old+active, old+unused, new+unused] */
    private function accounts(): array
    {
        DB::table('app_users')->whereNull('deleted_at')->update(['deleted_at' => now()]);
        $make = function (string $member, string $created, ?string $openedOn) {
            $id = $this->makeAppUser('ZR ' . $member, $member, ['verified' => 1]);
            DB::table('app_users')->where('id', $id)->update(['created_at' => $created]);
            if ($openedOn) {
                DB::table('user_activity_logs')->insert(['app_user_id' => $id, 'activity_date' => $openedOn, 'created_at' => $openedOn . ' 09:00:00']);
            }

            return $id;
        };

        return [
            $make('5900000901', '2026-09-24 00:00:00', '2026-10-01'),
            $make('5900000902', '2026-09-23 23:59:00', '2026-09-24'),
            $make('5900000903', '2024-03-12 10:00:00', null),
            $make('5900000904', '2026-11-20 10:00:00', null),
        ];
    }

    private function table(string $query): array
    {
        $url = '/admin/users?draw=1&start=0&length=100&columns[0][data]=id&columns[0][name]=id&columns[0][orderable]=true&order[0][column]=0&order[0][dir]=asc' . $query;
        $this->app->forgetInstance('datatables.request');

        return $this->getJson($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data');
    }

    public function test_the_activity_and_new_filters(): void
    {
        [$newActive, $oldActive, $oldUnused, $newUnused] = $this->accounts();
        $this->actingAs($this->adminUser(), 'admin');
        $ids = fn(string $q) => array_column($this->table($q), 'id');

        $this->assertSame([$newActive, $oldActive], $ids('&f_activity=yes'));
        $this->assertSame([$oldUnused, $newUnused], $ids('&f_activity=no'));
        // the tick box = everyone who opened the app since 24 Sept, whenever the account was created
        $this->assertSame([$newActive, $oldActive], $ids('&f_new=1'));
        $this->assertSame([], $ids('&f_new=1&f_activity=no'));
        $this->assertSame([$newActive, $oldActive, $oldUnused, $newUnused], $ids('&f_activity=zzz&f_new=7'), 'nonsense is ignored');
    }

    public function test_new_accounts_are_marked(): void
    {
        [$newActive, $oldActive] = $this->accounts();
        $this->actingAs($this->adminUser(), 'admin');
        $rows = collect($this->table(''))->keyBy('id');

        $this->assertStringStartsWith('2026-09-24 00:00 <span', $rows[$newActive]['created_at']);
        $this->assertStringContainsString('New</span>', $rows[$newActive]['created_at']);
        $this->assertSame('2026-09-23 23:59', $rows[$oldActive]['created_at']);
    }

    public function test_the_page_shows_counts_tags_and_keeps_them_out_of_the_report(): void
    {
        $this->accounts();
        $this->actingAs($this->adminUser(), 'admin');

        $html = $this->get('/admin/users')->assertOk()->getContent();
        $this->assertStringContainsString('Opened since 24 Sept - 2', $html);
        $this->assertStringContainsString('Not opened since 24 Sept - 2', $html);
        $this->assertStringContainsString('Only accounts that opened the app since 24 Sept', $html);
        $this->assertDoesNotMatchRegularExpression('/name="f_new" value="1"\s+checked/', $html, 'the page opens with all accounts');

        // ticked: the total equals the User Activity dashboard's count since 24 Sept
        $dashboard = DB::table('user_activity_logs')->join('app_users', 'app_users.id', '=', 'user_activity_logs.app_user_id')
            ->whereNull('app_users.deleted_at')->where('user_activity_logs.activity_date', '>=', '2026-09-24')
            ->distinct('app_users.member_id')->count('app_users.member_id');
        $this->assertCount($dashboard, $this->table('&f_new=1'));

        $html = $this->get('/admin/users?f_activity=yes&f_new=1')->assertOk()->getContent();
        $this->assertStringContainsString('Activity: <bdi>Opened since 24 Sept</bdi>', $html);
        $this->assertStringContainsString('Since 24 Sept: <bdi>Opened the app</bdi>', $html);
        $this->assertStringContainsString('change the table only', $html);
        $this->assertStringNotContainsString('type="hidden" name="f_activity"', $html);
        $this->assertStringNotContainsString('type="hidden" name="f_new"', $html);
        $this->assertMatchesRegularExpression('/name="f_new" value="1"\s+checked/', $html);
    }
}
