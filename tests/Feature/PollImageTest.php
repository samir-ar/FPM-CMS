<?php

namespace Tests\Feature;

use App\Http\Repositories\V2\ApiRepository;
use App\V2\Poll;
use Illuminate\Support\Facades\DB;
use Tests\Feature\NationalCouncil\CouncilTestCase;

// A poll can carry its own picture (shown under the options on the Home card). Without one the app keeps drawing the
// default poll picture, so the feed must send NO media in that case.
class PollImageTest extends CouncilTestCase
{
    private function makePoll(?string $image): Poll
    {
        $now = now();
        $id = DB::table('polls')->insertGetId([
            'question' => json_encode(['en' => 'Q', 'ar' => 'س'], JSON_UNESCAPED_UNICODE),
            'show' => 1, 'show_for_guest' => 0, 'strict_lang' => 'ar', 'image' => $image,
            'expiry_date' => $now->copy()->addDay(), 'created_at' => $now, 'updated_at' => $now,
        ]);

        return Poll::with('options')->find($id);
    }

    public function test_a_poll_without_a_picture_sends_no_media_so_the_default_stays(): void
    {
        $card = app(ApiRepository::class)->getGenericInstance($this->makePoll(null), 'POLL');

        $this->assertCount(0, $card['media']);
    }

    public function test_a_poll_with_a_picture_sends_it_as_media(): void
    {
        $card = app(ApiRepository::class)->getGenericInstance($this->makePoll('1700000000pic.jpg'), 'POLL');

        $this->assertCount(1, $card['media']);
        $this->assertSame('image', $card['media'][0]['type']);
        $this->assertStringContainsString('images/polls/1700000000pic.jpg', $card['media'][0]['thumbnail']);
    }

    public function test_the_create_and_edit_forms_have_the_picture_field(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        $poll = $this->makePoll('1700000000pic.jpg');

        $this->get('/admin/polls/create')->assertOk()->assertSee('name=\'image\'', false);
        $this->get('/admin/polls/' . $poll->id . '/edit')->assertOk()->assertSee('name=\'image\'', false)->assertSee('images/polls/1700000000pic.jpg', false);
    }
}
