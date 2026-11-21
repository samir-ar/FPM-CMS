<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Controllers\Api\V2\TalentController;
use App\Mail\TalentSubmissionReceived;
use App\Support\TalentNotifier;
use App\V2\AppUser;
use App\V2\TalentRole;
use App\V2\TalentSubmission;
use App\V2\TalentTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

// An e-mail goes to the addresses set on a track (Talent Tracks page) when a NEW form is sent: after the answer, short, no
// personal details beyond the name, never the CV; a mail failure never touches the submission.
class TalentEmailTest extends CouncilTestCase
{
    private function openPeer(array $attrs = []): TalentTrack
    {
        $t = TalentTrack::where('code', 'peer')->firstOrFail();
        $t->update($attrs + ['is_enabled' => true, 'opens_at' => now()->subHour(), 'closes_at' => now()->addDay(), 'max_per_member' => 10]);

        return $t->fresh();
    }

    private function sendPeerForm(int $userId, string $nominee = 'ZQ مرشح بريد'): array
    {
        $req = Request::create('/api/v2/talent-submit', 'POST', [
            'track' => 'peer', 'nominee_name' => $nominee, 'nominee_district' => 'المتن', 'nominee_town' => 'ضبية', 'profession' => 'مهندس',
            'relation_to_fpm' => 'مؤيد/ة للتيار', 'reasons' => 'سبب', 'strengths' => 'قوة', 'relationship_between' => 'علاقة صداقة',
            'roles' => [['id' => TalentRole::where('is_active', true)->orderBy('sort_order')->value('id')]], 'acknowledged' => '1',
        ], [], [], ['REMOTE_ADDR' => '10.8.8.8']);
        $req->merge(['user' => AppUser::withTrashed()->find($userId)]);
        app()->instance('request', $req);
        $res = app(TalentController::class)->submit($req);

        return json_decode($res->getContent(), true) ?? [];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Mail::fake();
    }

    public function test_addresses_are_cleaned_and_bad_ones_are_found(): void
    {
        $text = "A@fpmlb.org, b@fpmlb.org;\n  a@fpmlb.org  not-an-address ";
        $this->assertSame(['a@fpmlb.org', 'b@fpmlb.org'], TalentNotifier::parse($text), 'lower case, unique, only valid');
        $this->assertSame(['not-an-address'], TalentNotifier::invalid($text));
        $this->assertSame([], TalentNotifier::parse(null));
    }

    public function test_a_new_submission_sends_one_short_mail_after_the_response(): void
    {
        $this->openPeer(['notify_emails' => "hr@fpmlb.org\nboss@fpmlb.org"]);
        $u = $this->makeAppUser('T mail', '5900000020');

        $answer = $this->sendPeerForm($u);
        $this->assertTrue($answer['success']);
        Mail::assertNothingSent(); // not before the answer is out

        app()->terminate(); // the end of the request: the e-mail goes now
        Mail::assertSent(TalentSubmissionReceived::class, function (TalentSubmissionReceived $m) {
            $html = $m->render();
            $this->assertTrue($m->hasTo('hr@fpmlb.org') && $m->hasTo('boss@fpmlb.org'));
            $this->assertStringContainsString('ZQ مرشح بريد', $html);
            $this->assertStringContainsString('/admin/talent-submissions/', $html, 'it links to the CMS page');
            $this->assertStringNotContainsString('Test sender', $html);
            $this->assertStringContainsString('not attached', $html, 'the CV is never put in the mail');

            return str_contains($m->subject ?? $m->build()->subject, 'FPM Talent');
        });
        $this->assertSame(1, TalentSubmission::where('nominee_name', 'ZQ مرشح بريد')->count());
    }

    public function test_no_addresses_means_no_mail_and_a_duplicate_tap_sends_only_one(): void
    {
        $this->openPeer(['notify_emails' => null]);
        $u = $this->makeAppUser('T no mail', '5900000021');
        $this->sendPeerForm($u);
        app()->terminate();
        Mail::assertNothingSent();

        $this->openPeer(['notify_emails' => 'hr@fpmlb.org']);
        $u2 = $this->makeAppUser('T dup', '5900000022');
        $first = $this->sendPeerForm($u2, 'ZQ مرتين');
        $second = $this->sendPeerForm($u2, 'ZQ مرتين'); // a double tap: no new row
        $this->assertTrue($second['duplicate'] ?? false);
        app()->terminate();
        // (the test reuses one app, so the first form's waiting callback may run again: count only THIS form's mails)
        $mails = Mail::sent(TalentSubmissionReceived::class, fn ($m) => str_contains($m->render(), 'ZQ مرتين'));
        $this->assertCount(1, $mails, 'a double tap sends ONE e-mail');
    }

    public function test_a_mail_server_failure_does_not_touch_the_submission(): void
    {
        $this->openPeer(['notify_emails' => 'hr@fpmlb.org']);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP is down'));
        $u = $this->makeAppUser('T smtp down', '5900000023');

        $answer = $this->sendPeerForm($u, 'ZQ رغم العطل');
        app()->terminate();

        $this->assertTrue($answer['success']);
        $this->assertSame(1, TalentSubmission::where('nominee_name', 'ZQ رغم العطل')->count(), 'the form is saved even if the mail failed');
    }

    public function test_the_tracks_page_saves_the_addresses_refuses_bad_ones_and_sends_a_marked_test(): void
    {
        $admin = $this->actingAs($this->adminUser(), 'admin');
        $base = ['title' => 'عنوان', 'max_per_member' => '10'];

        $admin->post(route('admin.talent-tracks.update', [], false), ['tracks' => [
            'peer' => $base + ['notify_emails' => "HR@fpmlb.org, boss@fpmlb.org"],
            'self' => ['title' => 'y', 'max_per_member' => '1'],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame("hr@fpmlb.org\nboss@fpmlb.org", TalentTrack::where('code', 'peer')->value('notify_emails'));

        $this->actingAs($this->adminUser(), 'admin')->post(route('admin.talent-tracks.update', [], false), ['tracks' => [
            'peer' => $base + ['notify_emails' => 'good@fpmlb.org, oops'],
            'self' => ['title' => 'y', 'max_per_member' => '1'],
        ]])->assertSessionHasErrors();
        $this->assertSame("hr@fpmlb.org\nboss@fpmlb.org", TalentTrack::where('code', 'peer')->value('notify_emails'), 'nothing saved when one address is wrong');

        $this->actingAs($this->adminUser(), 'admin')->post(route('admin.talent-tracks.test-email', 'peer', false))->assertRedirect()->assertSessionHas('message');
        Mail::assertSent(TalentSubmissionReceived::class, function (TalentSubmissionReceived $m) {
            return $m->isTest && str_contains($m->render(), 'This is a test e-mail') && str_starts_with($m->build()->subject, '[TEST]');
        });

        // the other track has no address: the test says so instead of sending
        $this->actingAs($this->adminUser(), 'admin')->post(route('admin.talent-tracks.test-email', 'self', false))->assertSessionHasErrors();

        $page = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.talent-tracks.index', [], false))->assertOk()->getContent();
        $this->assertStringContainsString('E-mail me when a form is sent', $page);
        $this->assertStringContainsString('Send a test e-mail', $page);
    }
}
