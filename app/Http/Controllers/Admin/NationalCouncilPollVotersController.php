<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CouncilPollVotersExport;
use App\Exports\CouncilPollVotesExport;
use App\Http\Controllers\Controller;
use App\Support\CouncilMemberLookup;
use App\V2\CouncilNationalPoll;
use App\V2\CouncilRosterMember;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

// Two reports for a National Council poll, modelled on the Internal Election:
//  - the VOTERS page (progress): who may vote, who already has, when, and from which device. It never shows
//    what anybody voted, so it can be opened freely by whoever may view the polls;
//  - the hidden VOTES REPORT: who voted for whom. Unlinked, and behind the same one-time-code lock the
//    Internal Election's "who voted for whom" report uses.
class NationalCouncilPollVotersController extends Controller
{
    // One row per permitted voter: did he vote, when, from which device and phone account.
    private function voterRows(CouncilNationalPoll $poll): array
    {
        $permissions = DB::table('council_national_poll_permissions')->where('poll_id', $poll->id)->orderBy('id')->get();

        $votes = DB::table('council_national_poll_votes as v')
            ->leftJoin('app_users as u', 'u.id', '=', 'v.user_id')
            ->where('v.poll_id', $poll->id)
            ->get(['v.member_id', 'v.user_id', 'v.device_id', 'v.device_model', 'v.hardware_id', 'v.voted_at', 'v.weight', 'u.phone_number as phone', 'u.name as account_name']);
        // a poll with several questions has several vote rows per member: keep one per member
        $byMember = $votes->groupBy(fn($v) => (string) $v->member_id)->map(function ($rows) {
            $first = $rows->sortBy('voted_at')->first();
            $first->voted_at = $rows->min('voted_at');
            $first->device_id = $rows->pluck('device_id')->filter()->first();
            $first->device_model = $rows->pluck('device_model')->filter()->first();
            $first->hardware_id = $rows->pluck('hardware_id')->filter()->first();

            return $first;
        });

        $memberIds = $permissions->pluck('member_id')->map(fn($i) => (string) $i)->merge($byMember->keys())->unique()->values()->all();
        $details = CouncilMemberLookup::detailsFor($memberIds);
        $roles = CouncilRosterMember::whereIn('member_id', $memberIds)->pluck('role', 'member_id');

        // one PHONE = its hardware id (survives a reinstall of the app); for votes without one, the installation id.
        // A phone that voted for more than one member is the thing to look at.
        $keyOf = fn($v) => $v->hardware_id ?: $v->device_id;
        $devices = $byMember->filter(fn($v) => $keyOf($v))->groupBy($keyOf)->map(fn($g) => $g->pluck('member_id')->map(fn($i) => (string) $i)->unique()->values());

        $rows = [];
        foreach ($permissions as $p) {
            $id = (string) $p->member_id;
            $v = $byMember->get($id);
            $rows[$id] = [
                'member_id' => $id,
                'name' => $details[$id]->UserFullName ?? '',
                'role' => (string) ($roles[$id] ?? ''),
                'weight' => (int) $p->vote_weight,
                'voted' => (bool) $v,
                'voted_at' => $v ? $v->voted_at : null,
                'device' => $v ? ($v->device_id ?: '') : '',
                'hardware' => $v ? (string) $v->hardware_id : '',
                'device_key' => $v ? (string) $keyOf($v) : '',
                'device_model' => $v ? (string) $v->device_model : '',
                'device_members' => $v && $keyOf($v) ? $devices[$keyOf($v)]->count() : 0,
                'phone' => $v ? (string) $v->phone : '',
                'permitted' => true,
            ];
        }
        // a vote from someone no longer (or never) on the voter list: still shown, flagged
        foreach ($byMember as $id => $v) {
            if (!isset($rows[$id])) {
                $rows[$id] = [
                    'member_id' => (string) $id,
                    'name' => $details[$id]->UserFullName ?? '',
                    'role' => (string) ($roles[$id] ?? ''),
                    'weight' => (int) $v->weight,
                    'voted' => true,
                    'voted_at' => $v->voted_at,
                    'device' => $v->device_id ?: '',
                    'hardware' => (string) $v->hardware_id,
                    'device_key' => (string) $keyOf($v),
                    'device_model' => (string) $v->device_model,
                    'device_members' => $keyOf($v) ? $devices[$keyOf($v)]->count() : 0,
                    'phone' => (string) $v->phone,
                    'permitted' => false,
                ];
            }
        }

        return ['rows' => array_values($rows), 'devices' => $devices, 'details' => $details];
    }

    public function progress(Request $request, $id)
    {
        $poll = CouncilNationalPoll::findOrFail($id);
        $data = $this->voterRows($poll);
        $all = collect($data['rows']);

        $summary = [
            'voters' => $all->where('permitted', true)->count(),
            'voters_weight' => (int) $all->where('permitted', true)->sum('weight'),
            'voted' => $all->where('voted', true)->count(),
            'voted_weight' => (int) $all->where('voted', true)->sum('weight'),
        ];
        $summary['remaining'] = $summary['voters'] - $all->where('permitted', true)->where('voted', true)->count();
        $summary['turnout'] = $summary['voters'] ? round($all->where('permitted', true)->where('voted', true)->count() * 100 / $summary['voters']) : 0;
        $summary['turnout_weight'] = $summary['voters_weight'] ? round($all->where('permitted', true)->where('voted', true)->sum('weight') * 100 / $summary['voters_weight']) : 0;

        $status = in_array($request->query('status'), ['voted', 'not_voted'], true) ? $request->query('status') : 'all';
        $rows = $all;
        if ($status === 'voted') {
            $rows = $rows->where('voted', true);
        } elseif ($status === 'not_voted') {
            $rows = $rows->where('voted', false);
        }
        if ($request->filled('shared')) {
            $rows = $rows->where('device_members', '>', 1);
        }
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        if ($q !== '') {
            $rows = $rows->filter(fn($r) => str_contains(mb_strtolower($r['name'] . ' ' . $r['member_id'] . ' ' . $r['phone']), $q));
        }
        // voted first (newest at the top), then those still to vote
        $rows = $rows->sortBy([['voted', 'desc'], ['voted_at', 'desc']])->values();

        $shared = collect($data['devices'])->filter(fn($members) => $members->count() > 1)->map(function ($members, $device) use ($data) {
            return [
                'device' => $device,
                'model' => (string) (collect($data['rows'])->firstWhere('device_key', $device)['device_model'] ?? ''),
                'hardware' => (string) (collect($data['rows'])->firstWhere('device_key', $device)['hardware'] ?? ''),
                'device_id' => (string) (collect($data['rows'])->firstWhere('device_key', $device)['device'] ?? ''),
                'members' => $members->map(fn($m) => ['id' => $m, 'name' => $data['details'][$m]->UserFullName ?? $m])->all(),
            ];
        })->values();

        return view('cms.national_council_poll.progress')->with([
            'layout' => 'layouts.cms',
            'poll' => $poll,
            'summary' => $summary,
            'rows' => $rows,
            'status' => $status,
            'q' => $request->query('q', ''),
            'onlyShared' => $request->filled('shared'),
            'sharedDevices' => $shared,
        ]);
    }

    public function progressExport($id)
    {
        $poll = CouncilNationalPoll::findOrFail($id);
        $rows = collect($this->voterRows($poll)['rows'])->sortBy([['voted', 'desc'], ['voted_at', 'desc']])->values()->all();

        return Excel::download(new CouncilPollVotersExport($rows), 'council-poll-' . $poll->id . '-voters.xlsx');
    }

    // ---- the hidden "who voted for whom" report ----

    private function voteRows(CouncilNationalPoll $poll)
    {
        $votes = DB::table('council_national_poll_votes as v')
            ->leftJoin('council_national_poll_questions as q', 'q.id', '=', 'v.question_id')
            ->leftJoin('answer_council_national_polls as a', 'a.id', '=', 'v.answer_id')
            ->leftJoin('app_users as u', 'u.id', '=', 'v.user_id')
            ->where('v.poll_id', $poll->id)
            ->orderByDesc('v.voted_at')
            ->get(['v.member_id', 'v.weight', 'v.device_id', 'v.device_model', 'v.hardware_id', 'v.voted_at', 'v.question_id', 'v.answer_id',
                'q.question as question', 'a.answer as candidate', 'u.phone_number as phone', 'u.app_build as app_build']);

        $details = CouncilMemberLookup::detailsFor($votes->pluck('member_id')->map(fn($i) => (string) $i)->unique()->values()->all());
        // one PHONE = its hardware id (survives a reinstall); for votes without one, the installation id
        $keyOf = fn($v) => $v->hardware_id ?: $v->device_id;
        $deviceMembers = $votes->filter(fn($v) => $keyOf($v))->groupBy($keyOf)->map(fn($g) => $g->pluck('member_id')->unique()->count());
        $apps = \App\Support\MemberApp::forMembers($votes->pluck('member_id')->map(fn($i) => (string) $i)->unique()->values()->all());

        return $votes->map(fn($v) => [
            'voter' => $details[(string) $v->member_id]->UserFullName ?? '',
            'member_id' => (string) $v->member_id,
            'weight' => (int) $v->weight,
            'question_id' => (int) $v->question_id,
            'question' => (string) $v->question,
            'answer_id' => (int) $v->answer_id,
            'candidate' => (string) $v->candidate,
            'voted_at' => $v->voted_at,
            'device' => (string) $v->device_id,
            'hardware' => (string) $v->hardware_id,
            'device_model' => (string) $v->device_model,
            'device_members' => $keyOf($v) ? (int) $deviceMembers[$keyOf($v)] : 0,
            'phone' => (string) $v->phone,
            // the account that cast the vote first; the member's current account when that one never reported a build
            'app_version' => \App\Support\MemberApp::versionLabel($v->app_build) ?: ($apps[(string) $v->member_id]['version'] ?? ''),
        ]);
    }

    private function filteredVoteRows(CouncilNationalPoll $poll, Request $request)
    {
        $rows = $this->voteRows($poll);
        if ($request->filled('question_id')) {
            $rows = $rows->where('question_id', (int) $request->query('question_id'));
        }
        if ($request->filled('answer_id')) {
            $rows = $rows->where('answer_id', (int) $request->query('answer_id'));
        }
        if ($request->filled('shared')) {
            $rows = $rows->where('device_members', '>', 1);
        }

        return $rows->values();
    }

    public function votesReport(Request $request, $id)
    {
        $poll = CouncilNationalPoll::findOrFail($id);
        $all = $this->voteRows($poll);
        $rows = $this->filteredVoteRows($poll, $request);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $paged = new LengthAwarePaginator($rows->forPage($page, $perPage), $rows->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('cms.national_council_poll.votes_report')->with([
            'layout' => 'layouts.cms',
            'poll' => $poll,
            'votes' => $paged,
            'total' => $all->count(),
            'questions' => $all->unique('question_id')->map(fn($r) => ['id' => $r['question_id'], 'text' => $r['question']])->values(),
            'candidates' => $all->unique('answer_id')->map(fn($r) => ['id' => $r['answer_id'], 'text' => $r['candidate'], 'question_id' => $r['question_id']])->values(),
            'selectedQuestion' => (int) $request->query('question_id', 0),
            'selectedAnswer' => (int) $request->query('answer_id', 0),
            'onlyShared' => $request->filled('shared'),
        ]);
    }

    public function votesReportExport(Request $request, $id)
    {
        $poll = CouncilNationalPoll::findOrFail($id);

        return Excel::download(new CouncilPollVotesExport($this->filteredVoteRows($poll, $request)->all()), 'council-poll-' . $poll->id . '-who-voted-for-whom.xlsx');
    }
}
