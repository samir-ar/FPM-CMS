<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// The Representatives list can be filtered by category (drop-down above the table, ?category=ID).
class RepresentativesCategoryFilterTest extends CouncilTestCase
{
    public function test_the_list_filters_by_category_and_the_dropdown_remembers_it(): void
    {
        $now = now();
        $a = DB::table('dynamic_representatives')->insertGetId(['title' => json_encode(['ar' => 'ZQ فئة أ', 'en' => 'ZQ cat A']), 'text' => '{}', 'order' => 900, 'created_at' => $now, 'updated_at' => $now]);
        $b = DB::table('dynamic_representatives')->insertGetId(['title' => json_encode(['ar' => 'ZQ فئة ب', 'en' => 'ZQ cat B']), 'text' => '{}', 'order' => 901, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('persons')->insert([
            ['name' => json_encode(['ar' => 'ZQ شخص١', 'en' => 'ZQ p1']), 'dynamic_representative_id' => $a, 'order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => json_encode(['ar' => 'ZQ شخص٢', 'en' => 'ZQ p2']), 'dynamic_representative_id' => $a, 'order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => json_encode(['ar' => 'ZQ شخص٣', 'en' => 'ZQ p3']), 'dynamic_representative_id' => $b, 'order' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $admin = $this->adminUser();

        $page = $this->actingAs($admin, 'admin')->get(route('admin.representatives.index', ['category' => $a], false))->assertOk()->getContent();
        $this->assertStringContainsString('id=\'categoryFilter\'', $page);
        $this->assertStringContainsString("<option value='$a' selected>", $page);
        $this->assertStringContainsString("<option value='$b'>", $page);
        $this->assertStringContainsString("representatives?category=$a", $page, 'the table loads its rows with the same filter');

        $names = fn ($res) => collect($res->json('data'))->pluck('name')->map(fn ($n) => is_array($n) ? ($n['en'] ?? '') : (string) $n)->all();
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $filtered = $this->actingAs($admin, 'admin')->getJson(route('admin.representatives.index', ['category' => $a, 'draw' => 1, 'start' => 0, 'length' => 500], false), $ajax)->assertOk();
        $this->assertSame(2, $filtered->json('recordsTotal'));

        $all = $this->actingAs($admin, 'admin')->getJson(route('admin.representatives.index', ['draw' => 1, 'start' => 0, 'length' => 5], false), $ajax)->assertOk();
        $this->assertGreaterThanOrEqual(3, $all->json('recordsTotal'));
    }
}
