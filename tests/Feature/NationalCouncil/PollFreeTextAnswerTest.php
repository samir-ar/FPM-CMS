<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Controllers\Api\V2\ApiController;
use App\Http\Repositories\V2\ApiRepository;
use App\V2\AppUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// A poll option can let the member WRITE his own text (max 300 characters). The text is required on such an option,
// cleaned of tags, never accepted longer than the limit, ignored on a normal option, saved for members and guests, and
// visible to admins only (results page, Answers list): no API ever sends it back to a member.
class PollFreeTextAnswerTest extends CouncilTestCase
{
    private int $poll;
    private int $normal;
    private int $other;
    private string $body = '';

    protected function setUp(): void
    {
        parent::setUp();
        $now = now();
        $this->poll = DB::table('polls')->insertGetId(['question' => json_encode(['ar' => 'T text poll', 'en' => 'T text poll']), 'show' => 1, 'show_for_guest' => 1, 'expiry_date' => $now->copy()->addDay(), 'created_at' => $now, 'updated_at' => $now]);
        $this->normal = DB::table('polls_options')->insertGetId(['poll_id' => $this->poll, 'option' => json_encode(['ar' => 'نعم', 'en' => 'Yes']), 'allows_text' => 0, 'created_at' => $now, 'updated_at' => $now]);
        $this->other = DB::table('polls_options')->insertGetId(['poll_id' => $this->poll, 'option' => json_encode(['ar' => 'غير ذلك', 'en' => 'Other']), 'allows_text' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('groups_polls')->insert(['poll_id' => $this->poll, 'group_group_id' => 81, 'created_at' => $now, 'updated_at' => $now]);
    }

    private function answer(?int $userId, int $optionId, array $extra = []): int
    {
        $payload = ['option_id' => $optionId] + $extra + ($userId ? [] : ['device_id' => 'T-dev-' . uniqid(), 'hardware_id' => 'T-hw-' . uniqid()]);
        $req = Request::create('/api/v2/answer-poll', 'POST', $payload);
        if ($userId) {
            $req->merge(['user' => AppUser::withTrashed()->find($userId)]);
        }
        app()->instance('request', $req);
        $res = app(ApiController::class)->answerPoll($req, app(ApiRepository::class));
        $this->body = $res->getContent();

        return $res->getStatusCode();
    }

    public function test_a_member_answer_with_text_is_saved_cleaned_and_trimmed(): void
    {
        $u = $this->makeAppUser('T writer', '5700000001');

        $this->assertSame(200, $this->answer($u, $this->other, ['answer_text' => "  <b>أريد</b> مزيداً من <script>alert(1)</script>الشفافية\nشكراً  "]), $this->body);

        $saved = DB::table('users_polls')->where('poll_id', $this->poll)->value('answer_text');
        $this->assertSame("أريد مزيداً من alert(1)الشفافية\nشكراً", $saved, 'tags removed, ends trimmed, the line break kept');
    }

    public function test_the_text_is_required_on_a_text_option_and_limited_to_300_characters(): void
    {
        $u = $this->makeAppUser('T writer 2', '5700000002');

        foreach ([[], ['answer_text' => ''], ['answer_text' => "   \n  "], ['answer_text' => '<b></b>']] as $extra) {
            $this->assertSame(400, $this->answer($u, $this->other, $extra), 'an empty text is refused: ' . json_encode($extra));
        }
        $this->assertSame(400, $this->answer($u, $this->other, ['answer_text' => str_repeat('ب', 301)]), '301 characters are refused');
        $this->assertSame(0, DB::table('users_polls')->where('poll_id', $this->poll)->count(), 'nothing was saved by the refused tries');

        // exactly 300 (Arabic letters count as one character each) is accepted
        $this->assertSame(200, $this->answer($u, $this->other, ['answer_text' => str_repeat('ب', 300)]), $this->body);
        $this->assertSame(300, mb_strlen(DB::table('users_polls')->where('poll_id', $this->poll)->value('answer_text')));
    }

    public function test_a_normal_option_ignores_any_text(): void
    {
        $u = $this->makeAppUser('T plain', '5700000003');

        $this->assertSame(200, $this->answer($u, $this->normal, ['answer_text' => 'should not be kept']), $this->body);

        $this->assertNull(DB::table('users_polls')->where('poll_id', $this->poll)->value('answer_text'));
    }

    public function test_a_guest_can_write_text_too_and_still_answers_once(): void
    {
        $this->assertSame(200, $this->answer(null, $this->other, ['answer_text' => 'رأي ضيف']), $this->body);

        $this->assertSame('رأي ضيف', DB::table('guest_poll_votes')->where('poll_id', $this->poll)->value('answer_text'));
    }

    public function test_the_app_is_told_which_option_takes_text_but_never_gets_a_text_back(): void
    {
        $u = $this->makeAppUser('T reader', '5700000004');
        $this->answer($u, $this->other, ['answer_text' => 'سر لا يظهر للأعضاء']);
        $user = AppUser::find($u);

        $polls = app(ApiRepository::class)->getPollsByGroups($user, [81], 'dev', 'hw');
        $mine = $polls->firstWhere('id', $this->poll);
        $flags = collect($mine['options'])->pluck('allows_text', 'id')->all();

        $this->assertTrue($flags[$this->other]);
        $this->assertFalse($flags[$this->normal]);
        $this->assertStringNotContainsString('سر لا يظهر', json_encode($polls->all(), JSON_UNESCAPED_UNICODE), 'the written text never leaves the server towards a member');

        // an open poll (before answering) carries the flag too
        $fresh = app(ApiRepository::class)->getMappedOption(\App\V2\Poll::find($this->poll), 'ar');
        $this->assertTrue((bool) $fresh->firstWhere('id', $this->other)->allows_text);
    }

    public function test_admins_see_the_written_answers_escaped_on_the_results_page_and_in_the_answers_list(): void
    {
        $u = $this->makeAppUser('T shown', '5700000005');
        $this->answer($u, $this->other, ['answer_text' => 'نص <i>مهم</i> جداً']);
        $this->answer(null, $this->other, ['answer_text' => 'guest words']);
        // a raw row with markup, as if it had slipped in another way: the page must still escape it
        DB::table('guest_poll_votes')->where('poll_id', $this->poll)->update(['answer_text' => '<script>alert("x")</script> guest words']);

        $html = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.polls.results', $this->poll, false))->assertOk()->getContent();
        $this->assertStringContainsString('Written answers', $html);
        $this->assertStringContainsString('نص مهم جداً', $html);
        $this->assertStringNotContainsString('<script>alert("x")</script>', $html, 'a stored tag is escaped, never run');
        $this->assertStringContainsString('&lt;script&gt;', $html);

        $res = $this->actingAs($this->adminUser(), 'admin')->getJson(
            route('admin.userPolls.index', ['poll_id' => $this->poll, 'draw' => 1, 'start' => 0, 'length' => 50], false),
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();
        $texts = collect($res->json('data'))->pluck('answer_text')->filter()->implode(' | ');
        $this->assertStringContainsString('نص مهم جداً', $texts);
        $this->assertStringNotContainsString('<script>', $texts, 'the list escapes a tag too');
    }

    public function test_the_poll_form_remembers_which_option_takes_text(): void
    {
        $html = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.polls.edit', $this->poll, false))->assertOk()->getContent();

        preg_match_all('/<input type="checkbox"[^>]*name="options\[(\d+)\]\[allows_text\]"[^>]*>/', $html, $m, PREG_SET_ORDER);
        $tags = collect($m)->mapWithKeys(fn ($x) => [(int) $x[1] => $x[0]])->all();

        $this->assertCount(2, $tags, 'one tick box per saved option');
        $this->assertStringContainsString('checked', $tags[1], 'the text option is ticked');
        $this->assertStringNotContainsString('checked', $tags[0], 'the normal option is not');
    }
}
