<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// CMS > App Users: every column (except the Action button) can be sorted, by the value the cell shows.
class AppUsersSortingTest extends CouncilTestCase
{
    private const COLUMNS = [
        'id', 'member_id', 'name', 'phone_number', 'verified', 'created_at', 'last_activity', 'mobile', 'app_version',
        'district', 'town', 'sect', 'sect_number', 'last_unit_position', 'nashat_unit', 'noufous_unit', 'scan_checkin',
    ];

    private function fetch(string $column, string $direction, array $extra = [], int $length = 25): array
    {
        $query = ['draw' => 1, 'start' => 0, 'length' => $length, 'order' => [['column' => 0, 'dir' => $direction]],
            'columns' => [['data' => $column, 'name' => $column, 'orderable' => 'true', 'searchable' => 'false']]] + $extra;
        // DataTables keeps the first request of a test unless it is told to forget it (a real page load is a new process)
        $this->app->forgetInstance('datatables.request');

        return $this->getJson('/admin/users?' . http_build_query($query), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->json();
    }

    public function test_every_column_sorts_both_ways_without_losing_rows(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        $total = $this->fetch('id', 'desc')['recordsFiltered'];

        foreach (self::COLUMNS as $column) {
            foreach (['asc', 'desc'] as $direction) {
                $json = $this->fetch($column, $direction);
                $this->assertArrayNotHasKey('error', $json, "$column $direction");
                $this->assertSame($total, $json['recordsFiltered'], "$column $direction");
                $this->assertNotEmpty($json['data'], "$column $direction");
            }
        }
    }

    public function test_computed_columns_are_ordered_by_what_the_cell_shows_with_blanks_last(): void
    {
        $members = $this->sampleMemberIds(3);
        // only these three accounts stay visible, so the order is exactly theirs
        DB::table('app_users')->whereNull('deleted_at')->update(['deleted_at' => now()]);
        $old = $this->makeAppUser('T sort old', $members[0], ['verified' => 1, 'app_build' => 230040140, 'device_model' => 'Apple iPhone14,5']);
        $new = $this->makeAppUser('T sort new', $members[1], ['verified' => 1, 'app_build' => 230040143, 'device_model' => 'Samsung SM-S931B']);
        $none = $this->makeAppUser('T sort none', $members[2], ['verified' => 1]);
        DB::table('user_activity_logs')->insert(['app_user_id' => $old, 'activity_date' => '2026-01-10', 'created_at' => '2026-01-10 08:00:00']);
        DB::table('user_activity_logs')->insert(['app_user_id' => $new, 'activity_date' => '2026-01-20', 'created_at' => '2026-01-20 08:00:00']);

        $this->actingAs($this->adminUser(), 'admin');
        foreach (['last_activity', 'mobile', 'app_version'] as $column) {
            $this->assertSame([$old, $new, $none], array_column($this->fetch($column, 'asc')['data'], 'id'), "$column asc");
            $this->assertSame([$new, $old, $none], array_column($this->fetch($column, 'desc')['data'], 'id'), "$column desc");
        }

        foreach (['district', 'town', 'sect', 'nashat_unit'] as $column) {
            foreach (['asc', 'desc'] as $direction) {
                $shown = array_values(array_filter(array_column($this->fetch($column, $direction)['data'], $column), fn($v) => (string) $v !== ''));
                $ids = array_column($this->fetch($column, $direction)['data'], 'id');
                $this->assertCount(3, $ids);
                $sorted = DB::table('fpm_users')->whereIn(DB::raw('fpm_users.' . ($column === 'nashat_unit' ? 'NashatUnit' : $column)), $shown)
                    ->orderByRaw(($column === 'nashat_unit' ? 'NashatUnit' : $column) . ' ' . $direction)
                    ->pluck($column === 'nashat_unit' ? 'NashatUnit' : $column)->unique()->values()->all();
                $this->assertSame($sorted, array_values(array_unique($shown)), "$column $direction");
            }
        }
    }

    public function test_sorting_works_together_with_the_filters_and_the_search_box(): void
    {
        $member = $this->sampleMemberIds(1)[0];
        DB::table('app_users')->where('member_id', $member)->whereNull('deleted_at')->update(['deleted_at' => now()]);
        $id = $this->makeAppUser('T sort filtered', $member, ['verified' => 1]);

        $this->actingAs($this->adminUser(), 'admin');
        foreach (['mobile', 'district', 'last_activity'] as $column) {
            $json = $this->fetch($column, 'asc', ['f_member' => $member, 'search' => ['value' => '']]);
            $this->assertSame([$id], array_column($json['data'], 'id'), $column);
        }
    }

    public function test_the_page_offers_sorting_on_every_column_but_action(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        $html = $this->get('/admin/users')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '"sortable":false'));
    }
}
