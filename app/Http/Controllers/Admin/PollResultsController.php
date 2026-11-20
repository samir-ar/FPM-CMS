<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\V2\Poll;
use App\V2\PollOption;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// A results page for ONE regular poll: totals, share per option (bars + donut), members vs guests, and when the answers
// came in. Server-drawn and static (no animation): it reads the same two tables as the "User Polls" list
// (users_polls = members, guest_poll_votes = guests), so the two pages always agree.
class PollResultsController extends Controller
{
    // categorical slots in a fixed order (validated palette, light surface); the 8th slot is "Other"
    const SLOTS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6'];
    const OTHER = '#8a8a86';

    public function show($id)
    {
        $poll = Poll::findOrFail($id);
        $locale = in_array($poll->strict_lang, ['en', 'ar']) ? $poll->strict_lang : 'ar';

        $options = PollOption::withTrashed()->where('poll_id', $poll->id)->orderBy('id')->get();
        $members = DB::table('users_polls')->where('poll_id', $poll->id)->selectRaw('option_id, COUNT(*) AS c')->groupBy('option_id')->pluck('c', 'option_id');
        $guests = DB::table('guest_poll_votes')->where('poll_id', $poll->id)->selectRaw('option_id, COUNT(*) AS c')->groupBy('option_id')->pluck('c', 'option_id');

        $rows = [];
        foreach ($options as $i => $o) {
            $m = (int) ($members[$o->id] ?? 0);
            $g = (int) ($guests[$o->id] ?? 0);
            $rows[] = [
                'id' => $o->id,
                'text' => (string) $o->getTranslation('option', $locale),
                'removed' => $o->trashed(),
                'members' => $m,
                'guests' => $g,
                'count' => $m + $g,
                'color' => $i < count(self::SLOTS) ? self::SLOTS[$i] : self::OTHER,
            ];
        }

        $total = array_sum(array_column($rows, 'count'));
        $memberTotal = array_sum(array_column($rows, 'members'));
        $guestTotal = array_sum(array_column($rows, 'guests'));
        foreach ($rows as &$r) {
            $r['pct'] = $total > 0 ? round($r['count'] * 100 / $total, 1) : 0;
        }
        unset($r);

        // biggest first for reading; colours stay with the option, not with its rank
        $sorted = $rows;
        usort($sorted, fn ($a, $b) => [$b['count'], $a['id']] <=> [$a['count'], $b['id']]);

        $top = $total > 0 ? $sorted[0]['count'] : 0;
        $leaders = $top > 0 ? array_values(array_filter($sorted, fn ($r) => $r['count'] === $top)) : [];

        $expiry = $poll->expiry_date ? Carbon::parse($poll->expiry_date) : null;

        return view('cms.polls.results')->with([
            'poll' => $poll,
            'question' => (string) $poll->getTranslation('question', $locale),
            'rows' => $sorted,
            'total' => $total,
            'memberTotal' => $memberTotal,
            'guestTotal' => $guestTotal,
            'leaders' => $leaders,
            'expiry' => $expiry,
            'closed' => $expiry ? $expiry->isPast() : false,
            'timeline' => $this->timeline($poll->id),
            'generatedAt' => now(),
        ]);
    }

    // Answers over time: per hour when everything fits in 3 days, otherwise per day; empty buckets are filled in.
    private function timeline(int $pollId): array
    {
        $stamps = collect(DB::table('users_polls')->where('poll_id', $pollId)->pluck('created_at'))
            ->merge(DB::table('guest_poll_votes')->where('poll_id', $pollId)->pluck('created_at'))
            ->filter()
            ->map(fn ($t) => Carbon::parse($t))
            ->sort()
            ->values();

        if ($stamps->isEmpty()) {
            return ['unit' => 'hour', 'buckets' => []];
        }

        $unit = $stamps->first()->diffInHours($stamps->last()) <= 72 ? 'hour' : 'day';
        $fmt = $unit === 'hour' ? 'Y-m-d H:00' : 'Y-m-d';
        $counts = $stamps->countBy(fn ($t) => $t->format($fmt));

        $buckets = [];
        $cursor = $stamps->first()->copy()->startOf($unit);
        $end = $stamps->last()->copy()->startOf($unit);
        $guard = 0;
        while ($cursor <= $end && $guard++ < 400) {
            $key = $cursor->format($fmt);
            $buckets[] = ['label' => $key, 'short' => $unit === 'hour' ? $cursor->format('d M H:00') : $cursor->format('d M'), 'count' => (int) ($counts[$key] ?? 0)];
            $cursor->add(1, $unit);
        }

        return ['unit' => $unit, 'buckets' => $buckets];
    }
}
