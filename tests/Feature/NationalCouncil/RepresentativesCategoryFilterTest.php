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

    public function test_the_list_shows_no_broken_picture_for_a_person_without_a_photo_and_encodes_the_name(): void
    {
        // same as staging/production: the disk has a CDN url and the file name is appended as it is
        config(['filesystems.disks.s3.url' => 'https://cdn.example.com']);
        \Storage::forgetDisk('s3');
        $now = now();
        $c = DB::table('dynamic_representatives')->insertGetId(['title' => json_encode(['ar' => 'ZQ فئة ي', 'en' => 'ZQ cat Y']), 'text' => '{}', 'order' => 920, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('persons')->insert([
            ['name' => json_encode(['ar' => 'ZQ بلا صورة', 'en' => 'ZQ none']), 'dynamic_representative_id' => $c, 'order' => 1, 'image' => '', 'created_at' => $now, 'updated_at' => $now],
            ['name' => json_encode(['ar' => 'ZQ مع صورة', 'en' => 'ZQ with']), 'dynamic_representative_id' => $c, 'order' => 2, 'image' => 'Gebran+Bassil.jpg', 'created_at' => $now, 'updated_at' => $now],
            ['name' => json_encode(['ar' => 'ZQ عضو', 'en' => 'ZQ member']), 'dynamic_representative_id' => $c, 'order' => 3, 'image' => '', 'member_id' => '777000111', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $res = $this->actingAs($this->adminUser(), 'admin')->getJson(route('admin.representatives.index', ['category' => $c, 'draw' => 1, 'start' => 0, 'length' => 50], false), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $images = collect($res->json('data'))->sortBy('order')->pluck('image')->values();

        $this->assertSame('', (string) $images[0], 'no <img> for a person without a photo');
        $this->assertStringContainsString('Gebran%2BBassil.jpg', $images[1]);
        $this->assertStringContainsString('/api/v2/member-photo/777000111', $images[2], 'no uploaded photo + a Member ID: the member-list photo is shown');
    }
}
