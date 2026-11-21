<?php

namespace App\Support;

use App\Mail\TalentSubmissionReceived;
use App\V2\TalentSubmission;
use App\V2\TalentTrack;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// E-mail "a new FPM Talent form was sent" to the addresses set on the track (Talent Tracks page). It runs AFTER the app got
// its answer (the member never waits for the mail server) and a mail failure is only logged: it can never undo or fail a
// submission.
class TalentNotifier
{
    // "a@x.org, b@y.org; c@z.org\n d@w.org"  ->  ['a@x.org', 'b@y.org', ...] (only valid, unique addresses)
    public static function parse(?string $text): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/u', (string) $text, -1, PREG_SPLIT_NO_EMPTY) as $address) {
            $address = strtolower(trim($address));
            if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $out[$address] = $address;
            }
        }

        return array_values($out);
    }

    // the addresses in the text that are NOT valid (to tell the admin)
    public static function invalid(?string $text): array
    {
        return array_values(array_filter(
            preg_split('/[\s,;]+/u', (string) $text, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn ($a) => !filter_var(trim($a), FILTER_VALIDATE_EMAIL)
        ));
    }

    public static function submissionReceived(TalentSubmission $submission, bool $isTest = false, ?array $onlyTo = null): bool
    {
        $track = TalentTrack::where('code', $submission->track)->first();
        $to = $onlyTo ?? self::parse($track ? $track->notify_emails : null);
        if (!$to) {
            return false;
        }

        try {
            Mail::to($to)->send(new TalentSubmissionReceived($submission, $track, $isTest));

            return true;
        } catch (\Throwable $e) {
            Log::warning('talent_notification_failed', ['submission_id' => $submission->id, 'error' => mb_substr($e->getMessage(), 0, 300)]);

            return false;
        }
    }

    // Called by the API once a NEW submission is saved: sends after the response, never blocks the member.
    public static function afterSubmission(TalentSubmission $submission): void
    {
        $id = $submission->id;
        app()->terminating(function () use ($id) {
            $row = TalentSubmission::find($id);
            if ($row) {
                self::submissionReceived($row);
            }
        });
    }
}
