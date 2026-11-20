<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Repositories\V2\FpmApisRepository;
use App\V2\CouncilNationalPoll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// "Reset votes" for ONE council poll: clears every vote of the poll so it can be voted on again (testing, or a vote that
// must be re-run). Locked with a one-time code that is sent by SMS to the one fixed number in the system
// (config('app.internal_election_results_otp_phone')), whichever admin is logged in, so the admin password alone is
// not enough. Every vote is copied into council_poll_vote_resets before it is deleted, and the reset is logged.
class NationalCouncilPollResetController extends Controller
{
    const CODE_MINUTES = 5;
    const MAX_WRONG = 5;

    private function adminId()
    {
        return auth('admin')->id();
    }

    private function codeKey($pollId): string
    {
        return 'poll-reset-otp:' . $this->adminId() . ':' . $pollId;
    }

    private function titleOf(CouncilNationalPoll $poll): string
    {
        $t = $poll->title;
        return is_array($t) ? (string) ($t['ar'] ?? $t['en'] ?? '') : (string) $t;
    }

    public function form($id)
    {
        $poll = CouncilNationalPoll::findOrFail($id);

        return view('cms.national_council_poll.reset_votes')->with([
            'layout' => 'layouts.cms',
            'poll' => $poll,
            'pollTitle' => $this->titleOf($poll),
            'voteCount' => DB::table('council_national_poll_votes')->where('poll_id', $poll->id)->count(),
            'codeSentAt' => Cache::get($this->codeKey($poll->id) . ':sent_at'),
            'phoneSet' => (bool) config('app.internal_election_results_otp_phone'),
        ]);
    }

    public function sendCode(Request $request, $id)
    {
        $poll = CouncilNationalPoll::findOrFail($id);

        $throttle = 'poll-reset-otp-send:' . $this->adminId();
        if (Cache::has($throttle)) {
            return back()->withErrors('Please wait a minute before asking for another code.');
        }
        Cache::put($throttle, true, now()->addSeconds(60));

        $phone = config('app.internal_election_results_otp_phone');
        if (!$phone) {
            Log::warning('poll_reset_otp_phone_not_configured');
            return back()->withErrors('The code phone number is not configured on this server.');
        }

        $code = (string) random_int(100000, 999999);
        // encrypted at rest (the file cache is a plain file), valid for a few minutes, single use
        Cache::put($this->codeKey($poll->id), encrypt($code), now()->addMinutes(self::CODE_MINUTES));
        Cache::put($this->codeKey($poll->id) . ':sent_at', now(), now()->addMinutes(self::CODE_MINUTES));
        Cache::forget($this->codeKey($poll->id) . ':wrong');

        try {
            app(FpmApisRepository::class)->sendSMS([
                'phone_number' => $phone,
                'message' => 'FPM reset votes code: ' . $code,
            ]);
        } catch (\Throwable $e) {
            // The SMS provider often answers with a connection error AFTER sending the text, so the code stays valid:
            // look at the phone before asking for another one.
            Log::info('poll_reset_otp_sms_error', ['poll_id' => $poll->id, 'error' => substr(preg_replace('/(password|username)=[^&\s]*/i', '$1=***', $e->getMessage()), 0, 200)]);
            return back()->with('message', 'The SMS provider did not confirm. The code may still have arrived: check the phone before asking again.');
        }

        Log::info('poll_reset_otp_sent', ['poll_id' => $poll->id, 'admin_id' => $this->adminId(), 'ip' => $request->ip()]);

        return back()->with('message', 'A code was sent to the system phone.');
    }

    public function confirm(Request $request, $id)
    {
        $poll = CouncilNationalPoll::findOrFail($id);
        $request->validate(['code' => 'required|string|max:12']);

        $key = $this->codeKey($poll->id);
        $encrypted = Cache::get($key);
        $expected = null;
        if ($encrypted) {
            try {
                $expected = decrypt($encrypted);
            } catch (\Throwable $e) {
                $expected = null;
            }
        }

        if (!$expected || !hash_equals((string) $expected, trim($request->input('code')))) {
            $wrong = (int) Cache::get($key . ':wrong', 0) + 1;
            Cache::put($key . ':wrong', $wrong, now()->addMinutes(self::CODE_MINUTES));
            if ($wrong >= self::MAX_WRONG) {
                Cache::forget($key); // too many guesses: the code is burned, ask for a new one
            }
            Log::warning('poll_reset_otp_failed_attempt', ['poll_id' => $poll->id, 'admin_id' => $this->adminId(), 'ip' => $request->ip()]);
            return back()->withErrors('Incorrect or expired code.');
        }

        // single use
        Cache::forget($key);
        Cache::forget($key . ':sent_at');
        Cache::forget($key . ':wrong');

        $cleared = 0;
        DB::transaction(function () use ($poll, $request, &$cleared) {
            $votes = DB::table('council_national_poll_votes')->where('poll_id', $poll->id)->get();
            foreach ($votes->chunk(200) as $chunk) {
                DB::table('council_poll_vote_resets')->insert($chunk->map(fn ($v) => [
                    'poll_id' => $poll->id,
                    'vote_id' => $v->id,
                    'member_id' => $v->member_id,
                    'vote' => json_encode($v, JSON_UNESCAPED_UNICODE),
                    'reset_by' => $this->adminId(),
                    'reset_ip' => $request->ip(),
                    'reset_at' => now(),
                ])->all());
            }
            $cleared = DB::table('council_national_poll_votes')->where('poll_id', $poll->id)->delete();
        });

        Log::warning('poll_votes_reset', ['poll_id' => $poll->id, 'votes_cleared' => $cleared, 'admin_id' => $this->adminId(), 'ip' => $request->ip()]);

        return redirect()->route('admin.national-council-poll.index')
            ->with('message', "Poll reset: {$cleared} vote(s) cleared (a copy is kept in the backup table).");
    }
}
