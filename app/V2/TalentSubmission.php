<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class TalentSubmission extends Model
{
    protected $table = 'talent_submissions';
    protected $guarded = ['id'];
    protected $casts = ['is_guest' => 'boolean', 'acknowledged' => 'boolean'];

    const STATUSES = ['new' => 'New', 'shortlisted' => 'Shortlisted', 'accepted' => 'Accepted', 'rejected' => 'Rejected'];

    public function attachments()
    {
        return $this->hasMany(TalentAttachment::class, 'submission_id');
    }

    public function roles(): array
    {
        return json_decode((string) $this->roles_json, true) ?: [];
    }

    // answers to the questions the admin added in the CMS: [{key, label, value}]
    public function extras(): array
    {
        return json_decode((string) $this->extra_json, true) ?: [];
    }
}
