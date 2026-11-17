<?php

namespace App\Http\Controllers\Admin;

use App\Http\Traits\FileTrait;
use App\Http\Traits\FormTrait;
use App\Imports\PermittedUsersNationalCouncilPollImport;
use App\Exports\CouncilNationalPollTemplateExport;
use App\Exceptions\InvalidVotersFileException;
use App\V2\CouncilNationalPoll;
use App\V2\CouncilNationalPollPermission;
use App\V2\CouncilNationalPollVote;
use App\V2\CouncilMeeting;
use App\V2\CouncilRosterMember;
use App\Support\CouncilMemberLookup;
use App\V2\Group;
use Illuminate\Http\Request;
use DataTables;
use App\Http\Controllers\Controller;
use App\V2\AppUser;
use App\V2\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class NationalCouncilPollController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {
        if($request->ajax()) {

            $data = CouncilNationalPoll::all();

            return DataTables::of($data)

                ->addColumn('title', function($row){
                    return $row->title;
                })

                ->addColumn('created_at', function($row){
                    return $row->created_at;
                })


                ->addColumn('is_published', function($row){

                    return ($row->is_published)?"<span class='text-success'>Published</span>":"<span class='text-warning'>Not Published</span>";
                })

                ->addColumn('questions', function($row){
                    return "<a href='".route('admin.national-council-poll.questions.index',$row)."' class='text-success'>Questions</a>";
                })

                ->addColumn('results', function($row){
                    return "<a href='".route('admin.national-council-poll.results',$row->id)."' class='text-success'>النتائج</a>";
                })

                ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.national-council-poll.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.national-council-poll.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>".
                        ($row->is_published ? '' : "<a title='X DELETE ALL DATA X' data-toggle='modal' style='margin-left:10px;' class='delete-link' href='".route('admin.national-council-poll.clear', $row->id) . "'><i class='fa fa-window-close-o' style='color: red;' aria-hidden='true'></i></a>");

                })

                ->rawColumns(['is_published','action','questions','results'])

                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'إستبيان مجلس الوطني',
            'table_title' => '',
            'slug'		=> 'national-council-poll',
            'custom_btn' => "<a href='" . route('admin.national-council-poll.create') ."' class='btn btn-primary'>Add National Council Poll</a>",
            'headers'	=> ['id', 'Title', 'Publish','Results','questions','Creation Date','Action'],
            'action' => route('admin.national-council-poll.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' => 'title', 'name' => 'title'],
                ['data' => 'is_published', 'name' => 'is_published'],
                ['data' => 'results', 'name' => 'results'],
                ['data' => 'questions', 'name' => 'questions'],
                ['data' => 'created_at', 'name' => 'created_at'],
                ['data' =>  'action', 'name'=> 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function edit($id, Request $request)
    {
        $groups = Group::all()->pluck('name', 'group_id')->toArray();

        $poll = CouncilNationalPoll::find($id);

        $default_groups = !$poll->groups()->get()->isempty() ? $poll->groups()->get()->pluck('group_id')->toArray() : '';

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Edit Poll',
            'method'		=> 'update',
            'form_action'	=> route('admin.national-council-poll.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title', 'title', $poll->title, null, '', 'col-md-6 required'),
                        $this->drawHtml('checkbox', 'Publish', 'is_published', $poll->is_published, $poll->is_published, '', 'col-md-6'),
                        $this->drawHtml('select-box', 'Council meeting (optional)', 'meeting_id', (string) $poll->meeting_id, $this->meetingOptions(), null, 'col-md-6'),
                           ],
                ],
                [
                    'wrapper-class' => 'col-md-12 ',
                    'class' => 'box-primary',
                    'box-header' => 'Permissions',
                    'form_fields' => [
                        $this->drawHtml('file', 'List of permitted people', 'excel', '' , null, '', 'col-md-12 '),
                        "<p>Both columns are required: | Member ID | Weight | — <a href='" . route('admin.national-council-poll.voters-template') . "'>Download sample Excel</a>.</p>"
                    ]
                ],[
                    'wrapper-class' => 'col-md-12 ',
                    'class' => 'box-primary',
                    'box-header' => 'Permitted',
                    'form_fields' => [
                        $this->votersTools($id),
                        $this->getPermittedTable($id)
                    ]
                ],

            ]
        ]);
    }




    public function create(Request $request)
    {
        $groups = Group::all()->pluck('name', 'group_id')->toArray();

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'إضافة إستبيان مجلس وطني',
            'method'		=> 'post',
            'form_action'	=> route('admin.national-council-poll.store'),


                'boxes' => [
                    [
                        'wrapper-class' => 'col-md-12',
                        'class' => 'box-default',
                        'box-header' => 'Info',
                        'form_fields' => [
                            $this->drawHtml('small_text', 'Title', 'title', '', null, '', 'col-md-6 required'),
                            $this->drawHtml('checkbox', 'Publish', 'is_published', null, '', '', 'col-md-6'),
                            $this->drawHtml('select-box', 'Council meeting (optional)', 'meeting_id', '', $this->meetingOptions(), null, 'col-md-6'),
                            $this->drawHtml('checkbox', 'Load the voter list from the council roster', 'from_roster', null, '1', '', 'col-md-6'),
                        ],
                    ],  [
                        'wrapper-class' => 'col-md-12 ',
                        'class' => 'box-primary',
                        'box-header' => 'Permissions',
                        'form_fields' => [
                            $this->drawHtml('file', 'List of permitted people', 'excel', '' , null, '', 'col-md-12 '),
                            "<p>Both columns are required: | Member ID | Weight | — <a href='" . route('admin.national-council-poll.voters-template') . "'>Download sample Excel</a>.</p>"
                        ]
                    ]

                ]

        ]);
    }


    // Optional link from a poll to a council meeting (the poll then shows up
    // with that meeting's attendance on its results page).
    private function meetingOptions()
    {
        return ['' => '— بدون اجتماع —'] + CouncilMeeting::orderByDesc('id')->pluck('title', 'id')->toArray();
    }

    // Adds every active roster member that is not yet in this poll. Never
    // changes or removes anyone already there.
    private function syncFromRosterInto($pollId)
    {
        $existing = CouncilNationalPollPermission::where('poll_id', $pollId)->pluck('member_id')->flip();
        $added = 0;
        foreach (CouncilRosterMember::active()->get() as $member) {
            if (isset($existing[$member->member_id])) continue;
            CouncilNationalPollPermission::create([
                'poll_id' => $pollId,
                'member_id' => $member->member_id,
                'vote_weight' => $member->weight,
            ]);
            $added++;
        }
        return $added;
    }


    private function votersTools($pollId)
    {
        $search = route('admin.national-council-poll.voters.search', $pollId);
        $addOne = route('admin.national-council-poll.voters.add-one', $pollId);
        $sync = route('admin.national-council-poll.voters.sync-roster', $pollId);
        $csrf = csrf_token();

        return <<<HTML
<div style="margin-bottom:18px;">
  <p>
    <button type="button" id="syncRosterBtn" class="btn btn-default">Add missing members from the council roster</button>
    <small class="text-muted">adds only people not yet in this poll; nobody already here is changed or removed</small>
  </p>
  <div style="display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap;">
    <div style="position:relative; width:380px;">
      <input type="text" id="pvSearch" class="form-control" autocomplete="off" placeholder="Add one voter: search by name, phone or member id">
      <div id="pvResults" class="list-group" style="display:none; position:absolute; z-index:1000; width:100%; max-height:260px; overflow-y:auto; margin-top:2px;"></div>
    </div>
    <input type="hidden" id="pvMemberId">
    <input type="number" id="pvWeight" class="form-control" style="width:100px;" min="0" value="1" placeholder="Weight">
    <button type="button" id="pvAddBtn" class="btn btn-primary">Add voter</button>
  </div>
  <div id="pvMsg" class="alert" style="display:none; margin-top:10px;"></div>
</div>
<script>
(function () {
  function ready(fn) { if (window.jQuery) { fn(); } else { window.addEventListener('load', fn); } }
  ready(function () {
    var \$ = window.jQuery, csrf = '{$csrf}', t;
    function msg(ok, text) { \$('#pvMsg').removeClass('alert-success alert-danger').addClass(ok ? 'alert-success' : 'alert-danger').text(text).show(); }
    \$('#pvSearch').on('input', function () {
      clearTimeout(t); var q = \$(this).val(); \$('#pvMemberId').val('');
      if (q.length < 2) { \$('#pvResults').hide().empty(); return; }
      t = setTimeout(function () {
        \$.get('{$search}', { q: q }, function (data) {
          var h = data.length ? '' : '<div class="list-group-item">No results</div>';
          data.forEach(function (m) { h += '<a href="#" class="list-group-item pv-result" data-id="' + m.member_id + '">' + (m.name || '') + ' — ' + (m.phone || '') + ' — #' + m.member_id + '</a>'; });
          \$('#pvResults').html(h).show();
        });
      }, 300);
    });
    \$(document).on('click', '.pv-result', function (e) { e.preventDefault(); \$('#pvMemberId').val(\$(this).data('id')); \$('#pvSearch').val(\$(this).text()); \$('#pvResults').hide().empty(); });
    \$('#pvAddBtn').on('click', function () {
      var id = \$('#pvMemberId').val(); if (!id) { alert('Pick a member from the list first'); return; }
      \$.post('{$addOne}', { _token: csrf, member_id: id, weight: \$('#pvWeight').val() })
        .done(function (r) { msg(true, r.message); setTimeout(function () { location.reload(); }, 700); })
        .fail(function (x) { msg(false, (x.responseJSON && x.responseJSON.message) || 'Error'); });
    });
    \$('#syncRosterBtn').on('click', function () {
      if (!confirm('Add every council roster member that is not yet in this poll?')) return;
      \$.post('{$sync}', { _token: csrf })
        .done(function (r) { msg(true, r.message); setTimeout(function () { location.reload(); }, 900); })
        .fail(function (x) { msg(false, (x.responseJSON && x.responseJSON.message) || 'Error'); });
    });
    \$(document).on('click', '.pv-remove', function () {
      if (!confirm('Remove ' + \$(this).data('name') + ' from this poll?')) return;
      \$.ajax({ url: \$(this).data('url'), type: 'DELETE', data: { _token: csrf } })
        .done(function () { location.reload(); })
        .fail(function (x) { msg(false, (x.responseJSON && x.responseJSON.message) || 'Error'); });
    });
  });
})();
</script>
HTML;
    }

    private function getPermittedTable($pollId){
        // Joining directly to app_users on member_id fans one permission
        // row out into several displayed rows whenever a member has more
        // than one app_users account (a known, separate duplicate-accounts
        // issue) — look names up separately instead, one per member_id.
        $listPermissions = CouncilNationalPollPermission::where('poll_id', $pollId)->get();
        $names = AppUser::whereIn('member_id', $listPermissions->pluck('member_id'))
            ->get(['member_id', 'name'])
            ->unique('member_id')
            ->keyBy('member_id');

        $html = "<table class='table table-striped table-dark'>";

        $html .="
          <thead>
    <tr>
      <th scope='col'>Memeber Id</th>
      <th scope='col'>Name</th>
      <th scope='col'>Weight</th>
      <th scope='col'></th>
    </tr>
  </thead>";
        foreach ($listPermissions as $permission){
            $html .="<tr>";
            $html .= "<td>".$permission->member_id."</td>";
            $html .= "<td>".($names->get($permission->member_id)->name ?? '')."</td>";
            $html .= "<td>".$permission->vote_weight."</td>";
            $html .= "<td><button type='button' class='btn btn-xs btn-danger pv-remove' data-name='" . e($names->get($permission->member_id)->name ?? $permission->member_id) . "' data-url='" . route('admin.national-council-poll.voters.remove', [$pollId, $permission->id]) . "'>Remove</button></td>";
            $html .="</tr>";
        }

        $html .= "</table>";
        return $html;
    }

    public function update($id, Request $request)
    {


        $this->validate($request, [
            'Title' => 'required_with:details',
            //'groups' => 'required',
            //'excel' => 'required|mimes:xlsx,csv,xls'
        ]);
        $poll = CouncilNationalPoll::find($id);

        $poll->is_published = (request('is_published'))?true:false;
        $poll->title  = $request->title;
        $poll->meeting_id = $request->meeting_id ?: null;
        $poll->save();

        if ($request->excel) {
            try {
                // Wrapped in one transaction so a bad file rolls back the
                // delete too — the previous list stays intact on failure
                // instead of being wiped with nothing to replace it.
                DB::transaction(function () use ($poll, $request) {
                    CouncilNationalPollPermission::where('poll_id', $poll->id)->delete();
                    Excel::import(new PermittedUsersNationalCouncilPollImport($poll->id), $request->excel);
                });
            } catch (InvalidVotersFileException $e) {
                return redirect()->back()->withErrors(['excel' => $e->getMessage()])->withInput();
            }
        }
        //$poll->groups()->sync(request('groups'));

        return redirect()->route('admin.national-council-poll.index')->with('message', 'Poll has been updated successfully');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'Title' => 'required_with:details',
            'excel' => 'nullable|mimes:xlsx,csv,xls'
        ]);

        try {
            // Wrapped in one transaction — if the voters file is invalid,
            // the poll creation itself rolls back too, instead of leaving
            // an orphaned poll with zero voters behind.
            DB::transaction(function () use ($request) {
                $poll = new CouncilNationalPoll();
                $poll->is_published = (request('is_published'))?true:false;
                $poll->title  = $request->title;
                $poll->meeting_id = $request->meeting_id ?: null;
                $poll->save();

                if ($request->from_roster) {
                    $this->syncFromRosterInto($poll->id);
                }

                //Add the permitted list, if provided now — can also be added later via Edit
                if ($request->excel) {
                    Excel::import(new PermittedUsersNationalCouncilPollImport($poll->id), $request->excel);
                }
            });
        } catch (InvalidVotersFileException $e) {
            return redirect()->back()->withErrors(['excel' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.national-council-poll.index')->with('message', 'Poll has been created successfully');
    }


    public function searchVoters(Request $request, $id)
    {
        return response()->json(CouncilMemberLookup::search((string) $request->get('q', '')));
    }

    public function addVoter(Request $request, $id)
    {
        CouncilNationalPoll::findOrFail($id);
        $request->validate([
            'member_id' => 'required|integer|exists:fpm_users,MemberId',
            'weight' => 'required|integer|min:0|max:1000',
        ]);

        $memberId = (string) $request->member_id;
        $name = DB::table('fpm_users')->where('MemberId', $memberId)->value('UserFullName');

        if (CouncilNationalPollPermission::where('poll_id', $id)->where('member_id', $memberId)->exists()) {
            return response()->json(['message' => $name . ' is already in this poll.'], 422);
        }

        CouncilNationalPollPermission::create([
            'poll_id' => $id,
            'member_id' => $memberId,
            'vote_weight' => (int) $request->weight,
        ]);

        return response()->json(['message' => 'Added ' . $name . ' to this poll.']);
    }

    public function removeVoter($id, $permissionId)
    {
        $permission = CouncilNationalPollPermission::where('poll_id', $id)->where('id', $permissionId)->firstOrFail();

        if (CouncilNationalPollVote::where('poll_id', $id)->where('member_id', $permission->member_id)->exists()) {
            return response()->json(['message' => 'This member has already voted in this poll and cannot be removed.'], 422);
        }

        $permission->delete();

        return response()->json(['message' => 'Removed.']);
    }

    public function syncVotersFromRoster($id)
    {
        CouncilNationalPoll::findOrFail($id);
        $added = $this->syncFromRosterInto($id);

        return response()->json(['message' => $added . ' member(s) added from the council roster.']);
    }

    public function downloadVotersTemplate(){
        return Excel::download(new CouncilNationalPollTemplateExport(), 'national_council_poll_voters_template.xlsx');
    }


    public function destroy($id)
    {
        if (CouncilNationalPollVote::where('poll_id', $id)->exists()) {
            return back()->with('message', 'This poll already has votes and cannot be deleted.');
        }
        $poll = CouncilNationalPoll::find($id);
        $poll->delete();
        return back()->with('message', 'Poll Deleted Successfully');

    }

    public function results(Request $request, $id){

        $poll = CouncilNationalPoll::with('meeting')->find($id);
        $votes = CouncilNationalPollVote::with('user')->where("poll_id",$id)->get();

        // one row per member who voted (member-level, not per app account)
        $votersCount = $votes->groupBy(fn($v) => $v->member_id ?: 'u' . $v->user_id)->count();

        //Each elector is entered one time
        $electorsCount = CouncilNationalPollPermission::where('poll_id',$id)->count();
        $totalWeight = (int) CouncilNationalPollPermission::where('poll_id',$id)->sum('vote_weight');
        $votedWeight = (int) $votes->unique(fn($v) => ($v->member_id ?: 'u' . $v->user_id) . '|' . $v->question_id)
            ->groupBy('question_id')->map(fn($g) => $g->sum('weight'))->max();

        // ranked results per question: weighted total first, raw votes second
        $top = max(0, (int) $request->get('top', 0));
        $ranking = [];
        foreach ($poll->questions as $question) {
            $qVotes = $votes->where('question_id', $question->id);
            $castWeight = (int) $qVotes->sum('weight');

            $rows = $question->answers->map(function ($answer) use ($qVotes, $castWeight) {
                $a = $qVotes->where('answer_id', $answer->id);
                $w = (int) $a->sum('weight');
                return [
                    'answer' => $answer->answer,
                    'weight' => $w,
                    'votes' => $a->count(),
                    'pct' => $castWeight ? round($w * 100 / $castWeight, 1) : 0,
                ];
            })->sortByDesc(fn($r) => [$r['weight'], $r['votes']])->values();

            $rank = 0; $prev = null;
            $rows = $rows->map(function ($r, $i) use (&$rank, &$prev) {
                if ($prev === null || $r['weight'] !== $prev) { $rank = $i + 1; }
                $prev = $r['weight'];
                $r['rank'] = $rank;
                return $r;
            });

            // a tie across the cut-off line (e.g. 6th and 7th equal) needs a human decision
            $tieAtCutoff = $top > 0 && $rows->count() > $top && $rows[$top - 1]['weight'] === $rows[$top]['weight'] && $rows[$top]['weight'] > 0;

            $ranking[$question->id] = ['rows' => $rows, 'cast_weight' => $castWeight, 'tie_at_cutoff' => $tieAtCutoff];
        }

        $meetingStats = $poll->meeting ? $poll->meeting->stats() : null;

        $title = $poll->title;
        return view('council-election-votes.report',compact('poll','votes','votersCount','title','electorsCount','totalWeight','votedWeight','ranking','top','meetingStats'));
    }

    public function clear($id){
        if (CouncilNationalPoll::where('id', $id)->where('is_published', true)->exists()) {
            return redirect()->route('admin.national-council-poll.index')->with('message', 'A published poll\'s votes cannot be deleted. Unpublish it first.');
        }
        CouncilNationalPollVote::where('poll_id',$id)->get()->each(
            function ($vote){
                $vote->delete();
            }
        );
        return redirect()->route('admin.national-council-poll.index')->with('message', 'Data has been deleted');

    }
}
