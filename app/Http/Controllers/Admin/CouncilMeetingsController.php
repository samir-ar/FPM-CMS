<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CouncilRowsExport;
use App\Http\Controllers\Controller;
use App\Support\CouncilMemberLookup;
use App\V2\AppUser;
use App\V2\CouncilMeeting;
use App\V2\CouncilMeetingAttendance;
use App\V2\CouncilRosterMember;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

// A meeting of the National Council: its own attendance (QR scan in the app
// or manual search here, restricted to the roster) and a live quorum frozen
// from the roster when the meeting was created. Polls may optionally be
// attached to a meeting, but attendance never depends on them and never
// blocks anyone from voting - it only feeds the quorum.
class CouncilMeetingsController extends Controller
{
    public function index()
    {
        $meetings = CouncilMeeting::orderByDesc('id')->get();

        return view('cms.council_meetings.index')->with([
            'layout' => 'layouts.cms',
            'meetings' => $meetings->map(fn($m) => ['meeting' => $m, 'stats' => $m->stats()]),
        ]);
    }

    public function create()
    {
        $roster = CouncilRosterMember::active();
        $total = (int) (clone $roster)->sum('weight');

        return view('cms.council_meetings.create')->with([
            'layout' => 'layouts.cms',
            'memberCount' => (clone $roster)->count(),
            'totalWeight' => $total,
            'majority' => CouncilMeeting::majorityOf($total),
            'twoThirds' => CouncilMeeting::twoThirdsOf($total),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:191',
            'meeting_date' => 'nullable|date',
        ]);

        $meeting = new CouncilMeeting([
            'title' => $request->title,
            'meeting_date' => $request->meeting_date,
            'is_active' => true,
            'created_by_admin_id' => auth('admin')->id(),
        ]);
        $meeting->freezeFromRoster();
        $meeting->save();

        return redirect()->route('admin.council-meetings.show', $meeting->id)
            ->with('message', 'Meeting created. The quorum is frozen on the current council roster.');
    }

    public function show($id)
    {
        $meeting = CouncilMeeting::findOrFail($id);
        $roster = CouncilRosterMember::active()->orderBy('id')->get();
        $details = CouncilMemberLookup::detailsFor($roster->pluck('member_id')->all());

        $present = $meeting->attendance()->orderByDesc('checked_in_at')->get();
        $presentIds = $present->pluck('member_id')->flip();

        $absent = $roster->filter(fn($m) => !isset($presentIds[$m->member_id]))->values();

        $loggedIn = AppUser::whereIn('member_id', $roster->pluck('member_id'))
            ->pluck('member_id')->unique()->flip();
        $notLoggedIn = $roster->filter(fn($m) => !isset($loggedIn[$m->member_id]))->values();

        $admin = auth('admin')->user();

        return view('cms.council_meetings.show')->with([
            'layout' => 'layouts.cms',
            'meeting' => $meeting,
            'stats' => $meeting->stats(),
            'roster' => $roster->keyBy('member_id'),
            'details' => $details,
            'present' => $present,
            'absent' => $absent,
            'notLoggedIn' => $notLoggedIn,
            'loggedInCount' => $roster->count() - $notLoggedIn->count(),
            'rosterTotalNow' => (int) $roster->sum('weight'),
            'rosterCountNow' => $roster->count(),
            'canAdd' => $this->canAction('attendance.store'),
            'canExport' => $this->canAction('attendance.export'),
            'canManage' => $this->canManage(),
        ]);
    }

    // The live attendance board: a full-screen presentation page for the meeting room.
    // It starts with the data already inside the page (no flash of empty content) and
    // then refreshes itself from boardData().
    public function board($id)
    {
        $meeting = CouncilMeeting::findOrFail($id);

        return view('cms.council_meetings.board')->with([
            'meeting' => $meeting,
            'payload' => $this->boardPayload($meeting),
            'dataUrl' => route('admin.council-meetings.board-data', $meeting->id),
            'backUrl' => route('admin.council-meetings.show', $meeting->id),
        ]);
    }

    public function boardData($id)
    {
        return response()->json($this->boardPayload(CouncilMeeting::findOrFail($id)));
    }

    // Everything the board draws: the quorum numbers, every roster member with whether
    // they are present, the groups (by sector) with their totals, and the latest arrivals.
    private function boardPayload(CouncilMeeting $meeting): array
    {
        $roster = CouncilRosterMember::active()->orderBy('id')->get();
        $attendance = $meeting->attendance()->orderByDesc('checked_in_at')->get();
        $byMember = $attendance->keyBy('member_id');

        $ids = $roster->pluck('member_id')->merge($attendance->pluck('member_id'))->unique()->all();
        $details = CouncilMemberLookup::detailsFor($ids);
        $nameOf = fn($memberId) => $details[$memberId]->UserFullName ?? (string) $memberId;

        $members = $roster->map(function ($m) use ($byMember, $nameOf) {
            $a = $byMember->get($m->member_id);

            return [
                'id' => (string) $m->member_id,
                'name' => $nameOf($m->member_id),
                'role' => trim((string) $m->role),
                'sector' => trim((string) $m->sector) ?: 'Other',
                'district' => trim((string) $m->district),
                'weight' => (int) $m->weight,
                'present' => (bool) $a,
                'at' => $a ? optional($a->checked_in_at)->format('H:i') : null,
            ];
        })->values();

        $groups = $members->groupBy('sector')->map(function ($g, $name) {
            return [
                'name' => $name,
                'members' => $g->count(),
                'present' => $g->where('present', true)->count(),
                'weight' => (int) $g->sum('weight'),
                'weight_present' => (int) $g->where('present', true)->sum('weight'),
            ];
        })->sortByDesc('weight')->values();

        $recent = $attendance->take(10)->map(function ($a) use ($nameOf, $roster) {
            $role = optional($roster->firstWhere('member_id', $a->member_id))->role;

            return [
                'id' => (string) $a->member_id,
                'name' => $nameOf($a->member_id),
                'role' => trim((string) $role),
                'weight' => (int) $a->weight,
                'at' => optional($a->checked_in_at)->format('H:i:s'),
            ];
        })->values();

        $stats = $meeting->stats();

        return [
            'meeting' => [
                'title' => $meeting->title,
                'date' => optional($meeting->meeting_date)->format('d M Y'),
                'is_active' => (bool) $meeting->is_active,
            ],
            'stats' => $stats + ['absent_count' => max(0, $stats['member_count'] - $stats['present_count'])],
            'members' => $members,
            'groups' => $groups,
            'recent' => $recent,
            'server_time' => now()->format('H:i:s'),
        ];
    }

    public function stats($id)
    {
        return response()->json(CouncilMeeting::findOrFail($id)->stats());
    }

    // Restricted to the roster: nobody outside the council can be marked present.
    public function searchMembers(Request $request, $id)
    {
        $meeting = CouncilMeeting::findOrFail($id);
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $roster = CouncilRosterMember::active()->get()->keyBy('member_id');
        $found = CouncilMemberLookup::search($q, $roster->keys()->map(fn($k) => (string) $k)->all(), 15);
        $presentIds = $meeting->attendance()->pluck('member_id')->flip();

        return response()->json(array_map(function ($m) use ($roster, $presentIds) {
            $r = $roster[$m['member_id']] ?? null;
            $m['weight'] = $r->weight ?? null;
            $m['role'] = $r->role ?? null;
            $m['already_present'] = isset($presentIds[$m['member_id']]);
            return $m;
        }, $found));
    }

    public function storeAttendance(Request $request, $id)
    {
        $meeting = CouncilMeeting::findOrFail($id);

        if (!$meeting->is_active) {
            return $this->reply($request, 'This meeting is closed; attendance cannot be recorded.', 422);
        }

        $request->validate(['member_id' => 'required|digits_between:1,20']);
        $memberId = (string) $request->member_id;

        $rosterMember = CouncilRosterMember::active()->where('member_id', $memberId)->first();
        if (!$rosterMember) {
            return $this->reply($request, 'This person is not on the National Council roster.', 422);
        }

        $name = \DB::table('fpm_users')->where('MemberId', $memberId)->value('UserFullName') ?: $memberId;

        if ($meeting->attendance()->where('member_id', $memberId)->exists()) {
            return $this->reply($request, $name . ' is already marked present.', 422);
        }

        try {
            CouncilMeetingAttendance::create([
                'meeting_id' => $meeting->id,
                'member_id' => $memberId,
                'weight' => $rosterMember->weight,
                'checked_in_at' => now(),
                'added_by_admin_id' => auth('admin')->id(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return $this->reply($request, $name . ' is already marked present.', 422);
            }
            throw $e;
        }

        return $this->reply($request, $name . ' was marked present.');
    }

    public function destroyAttendance(Request $request, $id, $attendanceId)
    {
        $meeting = CouncilMeeting::findOrFail($id);
        $meeting->attendance()->where('id', $attendanceId)->delete();

        return $this->reply($request, 'Attendance cancelled.');
    }

    // Re-freezes this meeting's quorum from the roster as it is NOW.
    public function refreshQuorum(Request $request, $id)
    {
        $meeting = CouncilMeeting::findOrFail($id);
        $meeting->freezeFromRoster();
        $meeting->save();

        return back()->with('message', 'The quorum was updated from the current council roster.');
    }

    public function toggleActive($id)
    {
        $meeting = CouncilMeeting::findOrFail($id);
        $meeting->is_active = !$meeting->is_active;
        $meeting->save();

        return back()->with('message', $meeting->is_active ? 'Meeting opened.' : 'Meeting closed.');
    }

    public function destroy($id)
    {
        CouncilMeeting::findOrFail($id)->delete();

        return redirect()->route('admin.council-meetings.index')->with('message', 'Meeting deleted.');
    }

    public function exportAttendance($id)
    {
        $meeting = CouncilMeeting::findOrFail($id);
        $roster = CouncilRosterMember::orderBy('id')->get()->keyBy('member_id');
        $present = $meeting->attendance()->orderBy('checked_in_at')->get();
        $details = CouncilMemberLookup::detailsFor($present->pluck('member_id')->all());

        $rows = $present->map(fn($a) => [
            $a->member_id,
            $details[$a->member_id]->UserFullName ?? '',
            $details[$a->member_id]->MobileNumber ?? '',
            $roster[$a->member_id]->role ?? '',
            $roster[$a->member_id]->sector ?? '',
            $roster[$a->member_id]->district ?? '',
            $a->weight,
            optional($a->checked_in_at)->format('Y-m-d H:i:s'),
        ])->all();

        return Excel::download(new CouncilRowsExport(
            ['member_id', 'name', 'phone', 'role', 'sector', 'district', 'weight', 'checked_in_at'],
            $rows
        ), 'council-attendance-' . $meeting->id . '.xlsx');
    }

    // Roster members with no app login yet - who to chase before the meeting.
    public function exportReadiness($id)
    {
        CouncilMeeting::findOrFail($id);
        $roster = CouncilRosterMember::active()->orderBy('id')->get();
        $loggedIn = AppUser::whereIn('member_id', $roster->pluck('member_id'))->pluck('member_id')->unique()->flip();
        $missing = $roster->filter(fn($m) => !isset($loggedIn[$m->member_id]))->values();
        $details = CouncilMemberLookup::detailsFor($missing->pluck('member_id')->all());

        $rows = $missing->map(fn($m) => [
            $m->member_id,
            $details[$m->member_id]->UserFullName ?? '',
            $details[$m->member_id]->MobileNumber ?? '',
            $m->role,
            $m->weight,
        ])->all();

        return Excel::download(new CouncilRowsExport(
            ['member_id', 'name', 'phone', 'role', 'weight'],
            $rows
        ), 'council-not-logged-in.xlsx');
    }

    private function canAction(string $action): bool
    {
        $admin = auth('admin')->user();
        $pageId = \DB::table('pages')->where('url', 'admin.council-meetings.index')->value('id');

        return $admin && $pageId && ($admin->permissionLevel($pageId) === 'full' || $admin->hasExtraAction($pageId, $action));
    }

    private function canManage(): bool
    {
        $admin = auth('admin')->user();
        $pageId = \DB::table('pages')->where('url', 'admin.council-meetings.index')->value('id');

        return $admin && $pageId && $admin->permissionLevel($pageId) === 'full';
    }

    private function reply(Request $request, string $message, int $status = 200)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return back()->with('message', $message);
    }
}
