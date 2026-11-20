<?php

namespace Tests\Feature;

use App\Http\Repositories\V2\ApiRepository;
use App\V2\Poll;
use Illuminate\Support\Facades\DB;
use Tests\Feature\NationalCouncil\CouncilTestCase;

// The Home feed card draws only `title`. A poll has no title, so its question must be sent as the title,
// otherwise the card shows the options with no question above them.
class PollFeedQuestionTest extends CouncilTestCase
{
    private function makePoll(string $strictLang): Poll
    {
        $now = now();
        $id = DB::table('polls')->insertGetId([
            'question' => json_encode(['en' => 'Do you like the app?', 'ar' => 'هل يعجبك التطبيق؟'], JSON_UNESCAPED_UNICODE),
            'show' => 1, 'show_for_guest' => 0, 'strict_lang' => $strictLang,
            'expiry_date' => $now->copy()->addDay(), 'created_at' => $now, 'updated_at' => $now,
        ]);

        return Poll::with('options')->find($id);
    }

    public function test_a_poll_card_carries_its_question_as_the_title(): void
    {
        $poll = $this->makePoll('ar');
        $card = app(ApiRepository::class)->getGenericInstance($poll, 'POLL');

        $this->assertSame('هل يعجبك التطبيق؟', $card['title']);
        $this->assertSame('POLL', $card['item_type']);
    }

    public function test_an_english_only_poll_uses_the_english_question(): void
    {
        $card = app(ApiRepository::class)->getGenericInstance($this->makePoll('en'), 'POLL');

        $this->assertSame('Do you like the app?', $card['title']);
    }
}
