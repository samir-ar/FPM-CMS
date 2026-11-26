<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// CMS > Push Notification list: a notification without a picture shows an empty cell, not a broken image.
class NotificationListImageTest extends CouncilTestCase
{
    public function test_only_notifications_with_a_picture_show_one(): void
    {
        $without = DB::table('notifications')->insertGetId(['title' => 'ZR no picture', 'text' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $with = DB::table('notifications')->insertGetId(['title' => 'ZR picture', 'text' => 'x', 'image' => 'zr-test.jpg', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->adminUser(), 'admin');
        $url = '/admin/get-push-notification-page?draw=1&start=0&length=10&columns[0][data]=id&columns[0][name]=id&columns[0][orderable]=true&order[0][column]=0&order[0][dir]=desc';
        $rows = collect($this->getJson($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data'))->keyBy('id');

        $this->assertSame('', $rows[$without]['image']);
        $this->assertStringContainsString('<img', $rows[$with]['image']);
        $this->assertStringContainsString('images/notifications_images/zr-test.jpg', $rows[$with]['image']);
    }
}
