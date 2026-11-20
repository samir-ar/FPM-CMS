<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Controllers\Admin\InternalElectionController;
use App\Http\Repositories\V2\FpmApisRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

// The one-time code page shared by the internal election results / votes report and the council votes report.
// The SMS provider often answers with a connection error AFTER sending the text: the page must not show a red failure
// then, the code must stay valid, and the provider login inside the error must never reach the log.
class ResultsOtpSendTest extends CouncilTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.internal_election_results_otp_phone' => '96100000000']);
        Cache::flush();
    }

    public function test_a_provider_connection_error_is_not_shown_as_a_red_failure_and_the_code_stays_valid(): void
    {
        $this->mock(FpmApisRepository::class, function ($m) {
            $m->shouldReceive('sendSMS')->andThrow(new \RuntimeException('cURL error 56 for https://sms.example/api.php?username=I%20T&password=SECRETPASS&action=sends'));
        });
        Log::spy();

        $res = $this->actingAs($this->adminUser(), 'admin')->post(route('admin.internal-election.results-otp.send', [], false));

        $res->assertSessionHasNoErrors();
        $res->assertSessionHas('message', fn ($m) => str_contains($m, 'may still have arrived'));

        // the log line is written without the provider's login
        Log::shouldHaveReceived('info')->withArgs(function ($msg, $ctx = []) {
            return $msg === 'ie_results_otp_sms_failed' && !str_contains(json_encode($ctx), 'SECRETPASS') && str_contains(json_encode($ctx), 'password=***');
        })->once();

        // the code is still stored (so a text that did arrive can be used)
        $this->assertNotNull(Cache::get('ie-results-otp:' . session()->getId()));
    }

    public function test_a_normal_send_still_says_a_code_was_sent(): void
    {
        $sent = [];
        $this->mock(FpmApisRepository::class, function ($m) use (&$sent) {
            $m->shouldReceive('sendSMS')->andReturnUsing(function ($d) use (&$sent) { $sent[] = $d; return null; });
        });

        $this->actingAs($this->adminUser(), 'admin')->post(route('admin.internal-election.results-otp.send', [], false))
            ->assertSessionHas('message', 'A code was sent.');

        $this->assertCount(1, $sent);
        $this->assertSame('96100000000', $sent[0]['phone_number']);
    }
}
