<?php

namespace App\Mail;

use App\V2\TalentSubmission;
use App\V2\TalentTrack;
use Illuminate\Mail\Mailable;

// "A new FPM Talent form was sent". Deliberately short: the track, who, when, and the link to the CMS page (which needs a
// login and the right permission). No phone number, no e-mail, no CV: personal files are never put in an e-mail.
class TalentSubmissionReceived extends Mailable
{
    public TalentSubmission $submission;
    public ?TalentTrack $track;
    public string $link;
    public bool $isTest;

    public function __construct(TalentSubmission $submission, ?TalentTrack $track, bool $isTest = false)
    {
        $this->submission = $submission;
        $this->track = $track;
        $this->link = $isTest ? route('admin.talent-submissions.index') : route('admin.talent-submissions.show', $submission->id);
        $this->isTest = $isTest;
    }

    public function build()
    {
        $title = $this->track ? $this->track->title : $this->submission->track;

        return $this
            ->subject(($this->isTest ? '[TEST] ' : '') . 'FPM Talent: new submission - ' . $title)
            ->view('emails.talent_submission');
    }
}
