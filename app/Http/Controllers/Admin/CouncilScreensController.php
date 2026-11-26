<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\V2\CouncilMeeting;
use App\V2\CouncilNationalPoll;
use Illuminate\Support\Facades\DB;

// "Screens": the only page of a display account. It lists the National Council screens that are made to be shown on a TV:
// the live attendance board of a meeting, the live voting screen of a poll, and the results board.
class CouncilScreensController extends Controller
{
    public function index()
    {
        $meetings = CouncilMeeting::orderByDesc('is_active')->orderByDesc('meeting_date')->orderByDesc('id')->get()
            ->map(fn($m) => [
                'title' => $m->title,
                'detail' => optional($m->meeting_date)->format('d M Y'),
                'state' => $m->is_active ? 'LIVE' : 'CLOSED',
                'url' => route('admin.council-meetings.board', $m->id),
            ]);

        $polls = CouncilNationalPoll::orderByDesc('id')->get()->map(function ($p) {
            $closed = $p->closes_at && \Carbon\Carbon::parse($p->closes_at)->isPast();

            return [
                'title' => $p->title,
                'detail' => $p->closes_at ? 'closes ' . \Carbon\Carbon::parse($p->closes_at)->format('d M, H:i') : '',
                'state' => $p->is_published && !$closed ? 'LIVE' : ($closed ? 'CLOSED' : 'NOT OPEN'),
                'url' => route('admin.national-council-poll.board', $p->id),
            ];
        })->sortBy(fn($p) => ['LIVE' => 0, 'NOT OPEN' => 1, 'CLOSED' => 2][$p['state']])->values();

        return view('cms.council_screens.index')->with([
            'pageTitle' => 'Screens',
            'meetings' => $meetings,
            'polls' => $polls,
            'resultsUrl' => route('admin.national-council-poll.results-overview'),
            'resultsCount' => CouncilNationalPoll::where('show_in_results', 1)->count(),
        ]);
    }

    // Where a screen's Back button goes: its usual page when the account may open it, otherwise the Screens page.
    public static function backUrl(string $usualPage, string $usualUrl): string
    {
        $pageId = DB::table('pages')->where('url', $usualPage)->value('id');

        return $pageId !== null && auth('admin')->user()?->hasPage($pageId) ? $usualUrl : route('admin.council-screens.index');
    }
}
