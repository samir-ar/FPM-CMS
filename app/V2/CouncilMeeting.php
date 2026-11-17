<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class CouncilMeeting extends Model
{
    protected $fillable = [
        'title', 'meeting_date', 'member_count', 'total_weight',
        'quorum_majority', 'quorum_two_thirds', 'is_active', 'created_by_admin_id',
    ];

    protected $casts = ['is_active' => 'boolean', 'meeting_date' => 'date'];

    public function attendance()
    {
        return $this->hasMany(CouncilMeetingAttendance::class, 'meeting_id');
    }

    public function polls()
    {
        return $this->hasMany(CouncilNationalPoll::class, 'meeting_id');
    }

    // More than half of the total weight (755 -> 378, 756 -> 379).
    public static function majorityOf(int $totalWeight): int
    {
        return intdiv($totalWeight, 2) + 1;
    }

    // Two thirds of the total weight, rounded up (755 -> 504, 756 -> 504).
    public static function twoThirdsOf(int $totalWeight): int
    {
        return (int) ceil($totalWeight * 2 / 3);
    }

    // Copies the roster's CURRENT totals (active members and their weights) onto this
    // meeting: called when the meeting is created and by the explicit "refresh quorum"
    // action. The quorum is whatever the roster adds up to - nothing is typed in.
    public function freezeFromRoster(): void
    {
        $roster = CouncilRosterMember::active();

        $this->member_count = (clone $roster)->count();
        $this->total_weight = (int) (clone $roster)->sum('weight');
        $this->quorum_majority = self::majorityOf($this->total_weight);
        $this->quorum_two_thirds = self::twoThirdsOf($this->total_weight);
    }

    public function presentWeight(): int
    {
        return (int) $this->attendance()->sum('weight');
    }

    public function presentCount(): int
    {
        return $this->attendance()->count();
    }

    public function stats(): array
    {
        $present = $this->presentWeight();

        return [
            'present_count' => $this->presentCount(),
            'present_weight' => $present,
            'member_count' => (int) $this->member_count,
            'total_weight' => (int) $this->total_weight,
            'quorum_majority' => (int) $this->quorum_majority,
            'quorum_two_thirds' => (int) $this->quorum_two_thirds,
            'majority_reached' => $present >= $this->quorum_majority,
            'two_thirds_reached' => $present >= $this->quorum_two_thirds,
            'needed_majority' => max(0, $this->quorum_majority - $present),
            'needed_two_thirds' => max(0, $this->quorum_two_thirds - $present),
        ];
    }
}
