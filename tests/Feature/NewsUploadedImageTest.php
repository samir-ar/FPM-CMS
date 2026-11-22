<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Feature\NationalCouncil\CouncilTestCase;

// The News list and the edit page must show the photo the admin UPLOADED (gallery), not the old "source image"
// (often empty / a dead link for scraped news = broken picture icon).
class NewsUploadedImageTest extends CouncilTestCase
{
    private function news(?string $source): int
    {
        $id = DB::table('news')->insertGetId([
            'title' => json_encode(['en' => 'T', 'ar' => 'ت'], JSON_UNESCAPED_UNICODE),
            'source' => json_encode(['en' => 's', 'ar' => 'م'], JSON_UNESCAPED_UNICODE),
            'details' => json_encode(['en' => '<p>d</p>', 'ar' => '<p>ت</p>'], JSON_UNESCAPED_UNICODE),
            'source_image' => $source,
            'date' => now(), 'show_for_guest' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('groups_news')->insert(['news_id' => $id, 'group_group_id' => 81, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function listHtml(int $id): string
    {
        $json = $this->actingAs($this->adminUser(), 'admin')
            ->withHeaders(["X-Requested-With"=>"XMLHttpRequest","Accept"=>"application/json"])->get(route('admin.news.index', ['search' => ['value' => ''], 'draw' => 1, 'start' => 0, 'length' => 5, 'columns' => [['data' => 'id', 'name' => 'id', 'searchable' => 'true', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'desc']]], false))
            ->assertOk()->json();
        foreach ($json['data'] as $row) {
            if ((int) $row['id'] === $id) {
                return (string) $row['my_image'];
            }
        }
        $this->fail('news row not in the list');
    }

    public function test_list_shows_the_uploaded_image_and_not_the_source_image(): void
    {
        $id = $this->news('dead-source.jpg');
        DB::table('news_images')->insert(['news_id' => $id, 'name' => 'uploaded one.jpg', 'created_at' => now(), 'updated_at' => now()]);

        $img = $this->listHtml($id);
        $this->assertStringContainsString('images/news/uploaded%20one.jpg', $img);
        $this->assertStringNotContainsString('dead-source.jpg', $img);
    }

    public function test_list_falls_back_to_the_source_image_when_nothing_was_uploaded(): void
    {
        $id = $this->news('only-source.jpg');
        $this->assertStringContainsString('only-source.jpg', $this->listHtml($id));
    }

    public function test_edit_page_hides_the_source_preview_when_an_image_was_uploaded(): void
    {
        $id = $this->news('dead-source.jpg');
        DB::table('news_images')->insert(['news_id' => $id, 'name' => 'uploaded.jpg', 'created_at' => now(), 'updated_at' => now()]);
        $html = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.news.edit', $id, false))->assertOk()->getContent();
        $this->assertStringNotContainsString('dead-source.jpg', $html);
        $this->assertStringContainsString('uploaded.jpg', $html);

        $id2 = $this->news('only-source.jpg');
        $html2 = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.news.edit', $id2, false))->assertOk()->getContent();
        $this->assertStringContainsString('only-source.jpg', $html2);
    }
    public function test_the_image_uploader_shows_a_thumbnail_of_the_uploaded_picture(): void
    {
        $html = $this->actingAs($this->adminUser(), "admin")->get(route("admin.news.create", [], false))->assertOk()->getContent();
        $this->assertStringContainsString("showPreview: true", $html);
    }
}