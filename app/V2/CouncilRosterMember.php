<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

// A standing member of the National Council with his/her vote weight.
// This is the single list meetings (quorum + attendance) and polls (voter
// lists) are built from.
class CouncilRosterMember extends Model
{
    protected $fillable = ['member_id', 'weight', 'role', 'sector', 'district', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    // Name/phone live in fpm_users (the TWH-synced member table).
    public function fpmUser()
    {
        return $this->belongsTo(FpmUser::class, 'member_id', 'MemberId');
    }
}
