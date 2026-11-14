<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\V2\InternalElection;
use App\V2\InternalElectionPermission;
use App\V2\InternalElectionVote;
use App\V2\ElectionState;
use Carbon\Carbon;

// A standalone, non-admin page for monitoring live election turnout - added
// 2026-11-13, the day before the 2026-11-14 Internal Election, as a way to
// let a trusted non-admin person watch turnout without creating them an
// admin account or sharing the admin password. Deliberately outside the
// auth:admin group entirely and outside the Profile/page-perm system - a
// visitor here has no account to escalate from, so guessing any /admin URL
// just hits the normal admin login wall with no credentials behind it.
//
// Gated by its own username+password login (not a bare link - a link alone
// was rejected as insufficient since it could be forwarded/screenshotted
// without protection). The password is stored bcrypt-hashed
// (config('app.election_progress_password_hash')), never in plain text.
// After logging in, the viewer picks which currently-active election to
// watch (there can be more than one). Travels over HTTPS like the rest of
// the site, and the session cookie itself is marked secure (HTTPS-only).
class ElectionProgressPublicController extends Controller
{
    use \App\Http\Traits\DistrictMatchingTrait;
    use \App\Http\Traits\AppRegisteredVotersTrait;

    const SESSION_KEY = 'election_progress_authenticated';

    public function loginForm(Request $request)
    {
        if (!session(self::SESSION_KEY)) {
            return view('election-progress-login');
        }

        $elections = InternalElection::where('is_active', true)->orderByDesc('id')->get(['id', 'title']);

        return view('election-progress-list', compact('elections'));
    }

    public function login(Request $request)
    {
        $this->validate($request, [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $expectedUsername = config('app.election_progress_username');
        $expectedHash = config('app.election_progress_password_hash');

        $usernameOk = $expectedUsername && hash_equals((string) $expectedUsername, (string) $request->input('username'));
        $passwordOk = $expectedHash && password_verify((string) $request->input('password'), (string) $expectedHash);

        if (!$usernameOk || !$passwordOk) {
            \Log::warning('election_progress_login_failed', ['ip' => $request->ip()]);
            return back()->withErrors('Incorrect username or password.');
        }

        // New session id on login - standard protection against session
        // fixation (never reuse whatever session id existed before auth).
        $request->session()->regenerate();
        session([self::SESSION_KEY => true]);

        \Log::info('election_progress_login_success', ['ip' => $request->ip()]);

        return redirect()->route('election-progress.index');
    }

    public function logout(Request $request)
    {
        session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();

        return redirect()->route('election-progress.index');
    }

    public function show(Request $request, $electionId)
    {
        if (!session(self::SESSION_KEY)) {
            return redirect()->route('election-progress.index');
        }

        $election = InternalElection::where('id', $electionId)->where('is_active', true)->first();

        if (!$election) {
            abort(404);
        }

        $allPermittedMemberIds = InternalElectionPermission::where('election_id', $election->id)
            ->pluck('member_id')->filter()->unique()->values();

        $districtByMember = \DB::table('fpm_users')
            ->whereIn('MemberId', $allPermittedMemberIds)
            ->pluck('district', 'MemberId');

        $stateIds = \DB::table('internal_election_candidate_states')
            ->join('internal_election_candidates', 'internal_election_candidates.id', '=', 'internal_election_candidate_states.candidate_id')
            ->where('internal_election_candidates.election_id', $election->id)
            ->distinct()
            ->pluck('internal_election_candidate_states.election_state_id');
        $electionStateNames = ElectionState::whereIn('id', $stateIds)->pluck('name')->toArray();

        $permittedMemberIds = $allPermittedMemberIds->filter(function ($memberId) use ($districtByMember, $electionStateNames) {
            $district = $districtByMember->get($memberId) ?: 'بدون دائرة';
            return $this->districtMatchesAnyState($district, $electionStateNames);
        })->values();
        $totalPermitted = $permittedMemberIds->count();

        $votedMemberIds = InternalElectionVote::with('user')
            ->where('internal_election_id', $election->id)
            ->get()
            ->pluck('user.member_id')
            ->filter()
            ->map(fn($id) => ltrim((string) $id, '0'))
            ->unique();

        // Leading-zero tolerant, same as the admin progress page.
        $votedLookup = $votedMemberIds->flip();
        $totalVoted = $permittedMemberIds->filter(fn($m) => $votedLookup->has(ltrim((string) $m, '0')))->count();
        $totalRemaining = $totalPermitted - $totalVoted;
        $overallTurnout = $totalPermitted > 0 ? round($totalVoted / $totalPermitted * 100, 1) : 0;

        $votedSet = $votedMemberIds->flip();

        $districtStats = [];
        foreach ($permittedMemberIds as $memberId) {
            $district = $districtByMember->get($memberId) ?: 'بدون دائرة';
            if (!isset($districtStats[$district])) {
                $districtStats[$district] = ['permitted' => 0, 'voted' => 0];
            }
            $districtStats[$district]['permitted']++;
            if ($votedSet->has(ltrim((string) $memberId, '0'))) {
                $districtStats[$district]['voted']++;
            }
        }

        $districtRows = collect($districtStats)->map(function ($stats, $district) {
            return [
                'district' => $district,
                'permitted' => $stats['permitted'],
                'voted' => $stats['voted'],
                'remaining' => $stats['permitted'] - $stats['voted'],
                'turnout' => $stats['permitted'] > 0 ? round($stats['voted'] / $stats['permitted'] * 100, 1) : 0,
            ];
        })->sortByDesc('turnout')->values();

        $hourly = InternalElectionVote::where('internal_election_id', $election->id)
            ->whereNotNull('voted_at')
            ->get()
            ->groupBy(fn($vote) => Carbon::parse($vote->voted_at)->format('Y-m-d H:00'))
            ->map->count()
            ->sortKeys();

        // Second block: same figures against only the permitted voters who
        // are registered and logged in to the app (AppRegisteredVotersTrait).
        $appStats = $this->appRegisteredStats($permittedMemberIds, $districtByMember, $votedMemberIds, $votedSet);

        return view('election-progress-public', compact(
            'election', 'totalPermitted', 'totalVoted', 'totalRemaining', 'overallTurnout', 'districtRows', 'hourly'
        ))->with($appStats);
    }

    // Town drill-down for one district, same shape as the admin progress
    // page's AJAX endpoint - this session-gated copy exists so the modal
    // works here too without the viewer needing any admin permission.
    public function progressTowns(Request $request, $electionId)
    {
        if (!session(self::SESSION_KEY)) {
            abort(403);
        }

        $election = InternalElection::where('id', $electionId)->where('is_active', true)->first();
        if (!$election) {
            abort(404);
        }

        $district = $request->query('district');
        if (!$district) {
            return response()->json(['error' => 'Missing district'], 400);
        }

        $permittedMemberIds = InternalElectionPermission::where('election_id', $election->id)
            ->pluck('member_id')->filter()->unique()->values();

        $votedMemberIds = InternalElectionVote::with('user')
            ->where('internal_election_id', $election->id)
            ->get()
            ->pluck('user.member_id')
            ->filter()
            ->map(fn($id) => ltrim((string) $id, '0'))
            ->unique();
        $votedSet = $votedMemberIds->flip();

        // ?base=app: the "registered and logged in to the app" table's drilldown
        // - restrict to permitted voters who are registered in the app.
        if ($request->query('base') === 'app') {
            $registeredSet = $this->registeredMemberSet($votedMemberIds);
            $permittedMemberIds = $permittedMemberIds
                ->filter(fn($memberId) => $this->isAppRegistered($memberId, $registeredSet))
                ->values();
        }

        $fpmRows = \DB::table('fpm_users')
            ->whereIn('MemberId', $permittedMemberIds)
            ->get(['MemberId', 'district', 'town'])
            ->keyBy('MemberId');

        $districtMemberIds = $permittedMemberIds->filter(function ($memberId) use ($fpmRows, $district) {
            $row = $fpmRows->get($memberId);
            $voterDistrict = ($row && $row->district) ? $row->district : 'بدون دائرة';
            return $voterDistrict === $district;
        });

        $townStats = [];
        foreach ($districtMemberIds as $memberId) {
            $row = $fpmRows->get($memberId);
            $town = ($row && $row->town) ? $row->town : 'بدون بلدة';
            if (!isset($townStats[$town])) {
                $townStats[$town] = ['permitted' => 0, 'voted' => 0];
            }
            $townStats[$town]['permitted']++;
            if ($votedSet->has(ltrim((string) $memberId, '0'))) {
                $townStats[$town]['voted']++;
            }
        }

        $townRows = collect($townStats)->map(function ($stats, $town) {
            return [
                'town' => $town,
                'permitted' => $stats['permitted'],
                'voted' => $stats['voted'],
                'remaining' => $stats['permitted'] - $stats['voted'],
                'turnout' => $stats['permitted'] > 0 ? round($stats['voted'] / $stats['permitted'] * 100, 1) : 0,
            ];
        })->sortByDesc('turnout')->values();

        return response()->json(['district' => $district, 'towns' => $townRows]);
    }
}
