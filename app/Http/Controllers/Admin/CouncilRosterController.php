<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidVotersFileException;
use App\Exports\CouncilRowsExport;
use App\Http\Controllers\Controller;
use App\Imports\CouncilRosterImport;
use App\Support\CouncilMemberLookup;
use App\V2\AppUser;
use App\V2\CouncilMeeting;
use App\V2\CouncilRosterMember;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

// The standing list of National Council members and their vote weights.
// Meetings freeze their quorum from it; polls copy their voter list from it.
class CouncilRosterController extends Controller
{
    public function index()
    {
        $members = CouncilRosterMember::orderBy('id')->get();
        $details = CouncilMemberLookup::detailsFor($members->pluck('member_id')->all());
        $loggedIn = AppUser::whereIn('member_id', $members->pluck('member_id'))
            ->pluck('member_id')->unique()->flip();

        $active = $members->where('is_active', true);
        $total = (int) $active->sum('weight');

        return view('cms.council_roster.index')->with([
            'layout' => 'layouts.cms',
            'members' => $members,
            'details' => $details,
            'loggedIn' => $loggedIn,
            'memberCount' => $active->count(),
            'totalWeight' => $total,
            'majority' => CouncilMeeting::majorityOf($total),
            'twoThirds' => CouncilMeeting::twoThirdsOf($total),
        ]);
    }

    public function search(Request $request)
    {
        return response()->json(CouncilMemberLookup::search((string) $request->get('q', '')));
    }

    public function store(Request $request)
    {
        $request->validate([
            'member_id' => 'required|integer|exists:fpm_users,MemberId',
            'weight' => 'required|integer|min:0|max:1000',
            'role' => 'nullable|string|max:191',
            'sector' => 'nullable|string|max:191',
            'district' => 'nullable|string|max:191',
        ]);

        $memberId = (string) $request->member_id;
        $name = \DB::table('fpm_users')->where('MemberId', $memberId)->value('UserFullName');

        if (CouncilRosterMember::where('member_id', $memberId)->exists()) {
            return $this->reply($request, $name . ' is already in the council roster.', 422);
        }

        CouncilRosterMember::create([
            'member_id' => $memberId,
            'weight' => (int) $request->weight,
            'role' => $request->role,
            'sector' => $request->sector,
            'district' => $request->district,
        ]);

        return $this->reply($request, 'Added ' . $name . ' to the council roster.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'weight' => 'required|integer|min:0|max:1000',
            'role' => 'nullable|string|max:191',
            'sector' => 'nullable|string|max:191',
            'district' => 'nullable|string|max:191',
            'is_active' => 'nullable|boolean',
        ]);

        $member = CouncilRosterMember::findOrFail($id);
        $member->weight = (int) $request->weight;
        $member->role = $request->role;
        $member->sector = $request->sector;
        $member->district = $request->district;
        if ($request->has('is_active')) {
            $member->is_active = (bool) $request->is_active;
        }
        $member->save();

        return $this->reply($request, 'Saved.');
    }

    public function destroy(Request $request, $id)
    {
        $member = CouncilRosterMember::findOrFail($id);
        $member->delete();

        return $this->reply($request, 'The member was removed from the council roster.');
    }

    public function import(Request $request)
    {
        $request->validate(['excel' => 'required|file|mimes:xlsx,xls,csv']);

        $import = new CouncilRosterImport();
        try {
            \DB::transaction(function () use ($import, $request) {
                Excel::import($import, $request->file('excel'));
            });
        } catch (InvalidVotersFileException $e) {
            return redirect()->back()->withErrors(['excel' => $e->getMessage()]);
        }

        return redirect()->route('admin.council-roster.index')->with(
            'message',
            "Import done: {$import->added} added, {$import->updated} updated, {$import->skipped} rows skipped."
        );
    }

    public function template()
    {
        return Excel::download(new CouncilRowsExport(
            ['member_id', 'weight', 'role', 'sector', 'district'],
            [['900400001', '1', 'الرئيس', 'مجلس وطني', 'فئة 1'], ['2605200129', '1', 'مكتب تربوي', 'المهن', '']]
        ), 'council_roster_template.xlsx');
    }

    public function export()
    {
        $members = CouncilRosterMember::orderBy('id')->get();
        $details = CouncilMemberLookup::detailsFor($members->pluck('member_id')->all());

        $rows = $members->map(fn($m) => [
            $m->member_id,
            $details[$m->member_id]->UserFullName ?? '',
            $details[$m->member_id]->MobileNumber ?? '',
            $m->role,
            $m->sector,
            $m->district,
            $m->weight,
            $m->is_active ? 'yes' : 'no',
        ])->all();

        return Excel::download(new CouncilRowsExport(
            ['member_id', 'name', 'phone', 'role', 'sector', 'district', 'weight', 'active'],
            $rows
        ), 'council_roster.xlsx');
    }

    private function reply(Request $request, string $message, int $status = 200)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return back()->with('message', $message);
    }
}
