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
                    $url = route('admin.national-council-poll.toggle-publish', $row->id);

                    if ($row->is_published) {
                        return "<span class='text-success'>Published</span> " .
                            "<a href='{$url}' class='btn btn-xs btn-warning' style='margin-left:6px;' onclick=\"return confirm('Unpublish this poll? Members will no longer see it.');\">Unpublish</a>";
                    }

                    return "<span class='text-warning'>Not Published</span> " .
                        "<a href='{$url}' class='btn btn-xs btn-success' style='margin-left:6px;' onclick=\"return confirm('Publish this poll now? Members will be able to see and vote in it.');\">Publish</a>";
                })

                ->addColumn('closes', function($row){
                    $value = $row->closes_at ? \Carbon\Carbon::parse($row->closes_at)->format('Y-m-d\TH:i') : '';
                    $closed = $row->closes_at && \Carbon\Carbon::parse($row->closes_at)->isPast();

                    return "<div style='display:flex; gap:6px; align-items:center;'>".
                        "<input type='datetime-local' class='form-control poll-closes-at-input' style='width:200px' value='{$value}' data-id='{$row->id}'>".
                        "<button class='btn btn-primary btn-sm save-poll-closes-at-btn' data-id='{$row->id}'>Save</button>".
                        ($closed ? "<span class='label label-danger'>closed</span>" : '').
                        "</div>";
                })

                // "Show on results page": ticked = this poll is listed on the results board (published or not)
                ->addColumn('show_in_results', function($row){
                    return "<label style='font-weight:normal; cursor:pointer; margin:0;'>".
                        "<input type='checkbox' class='poll-show-results-tick' data-id='{$row->id}'" . ($row->show_in_results ? ' checked' : '') . "> Display".
                        "</label>";
                })

                ->addColumn('questions', function($row){
                    return "<a href='".route('admin.national-council-poll.questions.index',$row)."' class='text-success'>Questions</a>";
                })

                ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.national-council-poll.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a title='Who voted' style='margin:0 12px;' href='" . route('admin.national-council-poll.progress', $row->id) . "'><i class='fa fa-users' aria-hidden='true'></i></a>".
                        "<a title='Reset votes (SMS code)' style='margin:0 12px 0 0;' href='" . route('admin.national-council-poll.reset-votes', $row->id) . "'><i class='fa fa-undo' style='color:#dd4b39;' aria-hidden='true'></i></a>".
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.national-council-poll.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                    // the red "X DELETE ALL DATA X" button was removed from this list on purpose (a single mis-click
                    // wiped a poll's votes); the page itself is not linked from here any more
                })

                ->rawColumns(['is_published','closes','action','questions','show_in_results'])

                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'إستبيان مجلس الوطني',
            'table_title' => '',
            'slug'		=> 'national-council-poll',
            'custom_btn' => "<a href='" . route('admin.national-council-poll.create') ."' class='btn btn-primary'>Add National Council Poll</a> <a href='" . route('admin.national-council-poll.results-overview') ."' class='btn btn-default' style='margin-left:6px;'>Results of several polls</a>",
            'headers'	=> ['id', 'Title', 'Publish','Closes at','Show on results page','questions','Creation Date','Action'],
            // the version number makes browsers fetch the new script after a change instead of keeping an old cached copy
            'scripts' => ['/js/council-poll-list.js?v=' . (filemtime(public_path('js/council-poll-list.js')) ?: 1)],
            'action' => route('admin.national-council-poll.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' => 'title', 'name' => 'title'],
                ['data' => 'is_published', 'name' => 'is_published'],
                ['data' => 'closes', 'name' => 'closes', 'searchable' => false, 'sortable' => false],
                ['data' => 'show_in_results', 'name' => 'show_in_results', 'searchable' => false, 'sortable' => false],
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
                        // the 5th argument is the value the tickbox SENDS; it used to be the poll's current state
                        // (0 when unpublished), so ticking Publish sent "0" and the poll stayed unpublished
                        $this->drawHtml('checkbox', 'Publish', 'is_published', $poll->is_published, '1', '', 'col-md-6'),
                        $this->drawHtml('select-box', 'Council meeting (optional)', 'meeting_id', (string) $poll->meeting_id, $this->meetingOptions(), null, 'col-md-6'),
                        $this->drawHtml('date-time-picker', 'Closes at (optional; after this time the poll shows as closed in the app and nobody can vote)', 'closes_at', $poll->closes_at ? \Carbon\Carbon::parse($poll->closes_at)->format('Y-m-d\TH:i') : '', null, '', 'col-md-6'),
                        $this->drawHtml('number', 'Number of winners (seats) - on a tie in weight the older candidate wins', 'winners', $poll->winners, null, '1', 'col-md-6'),
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
                            $this->drawHtml('checkbox', 'Publish', 'is_published', null, '1', '', 'col-md-6'),
                            $this->drawHtml('select-box', 'Council meeting (optional)', 'meeting_id', '', $this->meetingOptions(), null, 'col-md-6'),
                            $this->drawHtml('date-time-picker', 'Closes at (optional; after this time the poll shows as closed in the app and nobody can vote)', 'closes_at', '', null, '', 'col-md-6'),
                            $this->drawHtml('number', 'Number of winners (seats) - on a tie in weight the older candidate wins', 'winners', '', null, '1', 'col-md-6'),
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

    // Save the closing time of one poll from the polls list (empty = no closing time).
    public function updateClosesAt(Request $request, $id)
    {
        $request->validate(['closes_at' => 'nullable|date']);

        $poll = CouncilNationalPoll::find($id);
        if (!$poll) {
            return response()->json(['message' => 'Poll not found'], 404);
        }

        $poll->closes_at = $request->closes_at ? \Carbon\Carbon::parse($request->closes_at)->toDateTimeString() : null;
        $poll->save();

        return response()->json(['message' => 'Closing time updated']);
    }

    // The tick "Show on results page" of one poll (saved by a click in the polls list).
    public function updateShowInResults(Request $request, $id)
    {
        $poll = CouncilNationalPoll::find($id);
        if (!$poll) {
            return response()->json(['message' => 'Poll not found'], 404);
        }

        $poll->show_in_results = $request->boolean('show');
        $poll->save();

        return response()->json(['message' => $poll->show_in_results ? 'Shown on the results page' : 'Hidden from the results page', 'show' => (bool) $poll->show_in_results]);
    }

    // One click Publish / Unpublish from the polls list. A poll with no question cannot be published (members
    // would see an empty poll); unpublishing is always allowed.
    public function togglePublish($id)
    {
        $poll = CouncilNationalPoll::findOrFail($id);

        if (!$poll->is_published) {
            if (!$poll->questions()->exists()) {
                return redirect()->route('admin.national-council-poll.index')
                    ->withErrors('"' . $poll->title . '" has no question yet. Add the question and its candidates first, then publish it.');
            }
        }

        $poll->is_published = !$poll->is_published;
        $poll->save();

        return redirect()->route('admin.national-council-poll.index')
            ->with('message', '"' . $poll->title . '" is now ' . ($poll->is_published ? 'published' : 'unpublished') . '.');
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
        $poll->closes_at = $request->closes_at ? \Carbon\Carbon::parse($request->closes_at)->toDateTimeString() : null;
        $poll->winners = $this->winnersFrom($request);
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
                $poll->closes_at = $request->closes_at ? \Carbon\Carbon::parse($request->closes_at)->toDateTimeString() : null;
                $poll->winners = $this->winnersFrom($request);
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
        $poll = CouncilNationalPoll::find($id);
        if (!$poll) {
            return back()->with('message', 'Poll was already deleted');
        }

        // A poll that is live cannot be deleted by accident: unpublish it first.
        if ($poll->is_published) {
            return back()->withErrors('"' . $poll->title . '" is published. Unpublish it first, then delete it.');
        }

        // Only MARKS the poll as deleted: it disappears from the CMS list and from the app, but the poll, its
        // questions, its voters and every vote stay in the database.
        $poll->delete();

        return back()->with('message', 'Poll deleted. Its data (questions, voters and votes) is kept in the database.');

    }

    public function results(Request $request, $id){

        $poll = CouncilNationalPoll::with('meeting')->find($id);
        // how many seats: the ?top= box if given, otherwise what was set on the poll
        $top = max(0, (int) $request->get('top', 0)) ?: (int) $poll->winners;

        $data = $this->resultsData($poll, $top);
        $data['title'] = $poll->title;

        return view('council-election-votes.report', $data);
    }

    // "Number of winners" from the form: a whole number from 1 to 200, or empty (= not set).
    private function winnersFrom(Request $request): ?int
    {
        $n = (int) $request->get('winners');

        return $n >= 1 ? min($n, 200) : null;
    }

    // GLOBAL results page: tick one or several polls and see the results of all the ticked ones on one page.
    // Same permission as the single-poll results (Council polls, view). Nothing here changes any data.
    public function resultsOverview(Request $request)
    {
        // the board lists exactly the polls ticked "Show on results page" in the polls list - published or not
        // (a published poll is ticked automatically when this shipped; a new poll starts unticked)
        $polls = CouncilNationalPoll::where('show_in_results', 1)->orderByDesc('id')->get(['id', 'title', 'is_published', 'closes_at', 'winners', 'created_at'])->map(function ($p) {
            // what the "choose a poll" cards show
            $p->electors = CouncilNationalPollPermission::where('poll_id', $p->id)->count();
            $p->voters = CouncilNationalPollVote::where('poll_id', $p->id)->distinct()->count(DB::raw("COALESCE(NULLIF(member_id,''), CONCAT('u', user_id))"));
            $p->question_count = $p->questions()->count();

            return $p;
        });

        // the page works on ONE poll at a time; an old link with several ids opens the first
        $chosen = collect((array) $request->get('polls', []))->map(fn($v) => (int) $v)->filter()->unique()->values()->take(1);
        $top = max(0, (int) $request->get('top', 0));

        return view('cms.national_council_poll.results_overview')->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'نتائج المجلس الوطني',
            'polls' => $polls,
            'chosen' => $chosen->all(),
            'top' => $top,
            // first paint comes with the page; after that the page asks resultsOverviewData() every few seconds
            'initial' => $this->resultsPayload($chosen->all(), $top),
            'dataUrl' => route('admin.national-council-poll.results-overview.data'),
            'backUrl' => CouncilScreensController::backUrl('admin.national-council-poll.index', route('admin.national-council-poll.index')),
        ]);
    }

    // JSON for the live results page (same permission as the page; read-only).
    public function resultsOverviewData(Request $request)
    {
        $chosen = collect((array) $request->get('polls', []))->map(fn($v) => (int) $v)->filter()->unique()->values()->all();
        $top = max(0, (int) $request->get('top', 0));

        return response()->json($this->resultsPayload($chosen, $top));
    }

    // Everything the live page draws, as plain arrays: per chosen poll its headline numbers and, per question,
    // the ranked rows (with the candidate's photo when he was picked from the member list).
    private function resultsPayload(array $pollIds, int $top): array
    {
        $out = [];
        foreach ($pollIds as $pollId) {
            $poll = CouncilNationalPoll::with('meeting', 'questions.answers')->find($pollId);
            if (!$poll || !$poll->show_in_results) {
                continue;
            }

            // seats: the "Mark the top" box when filled, otherwise the number of winners set on this poll
            $seats = $top ?: (int) $poll->winners;
            $d = $this->resultsData($poll, $seats);
            $questions = [];
            foreach ($poll->questions as $question) {
                $r = $d['ranking'][$question->id];
                $questions[] = [
                    'id' => $question->id,
                    'text' => $question->question,
                    'cast_weight' => $r['cast_weight'],
                    'tie_at_cutoff' => $r['tie_at_cutoff'],
                    'rows' => $r['rows']->map(fn($row) => [
                        'id' => $row['id'],
                        'name' => $row['answer'],
                        'photo' => $row['member_id'] ? url('/api/v2/member-photo/' . $row['member_id']) : null,
                        'weight' => $row['weight'],
                        'votes' => $row['votes'],
                        'pct' => $row['pct'],
                        'rank' => $row['rank'],
                        'won_by_age' => $row['won_by_age'],
                        'tied_with_older' => $row['tied_with_older'],
                    ])->values()->all(),
                ];
            }

            $out[] = [
                'id' => $poll->id,
                'title' => $poll->title,
                'published' => (bool) $poll->is_published,
                'closes_at' => $poll->closes_at ? \Carbon\Carbon::parse($poll->closes_at)->format('d M Y H:i') : null,
                'closed' => $poll->isClosed(),
                'top' => $seats,
                'electors' => $d['electorsCount'],
                'voters' => $d['votersCount'],
                'turnout' => $d['electorsCount'] ? round($d['votersCount'] * 100 / $d['electorsCount'], 1) : 0,
                'total_weight' => $d['totalWeight'],
                'voted_weight' => $d['votedWeight'],
                'questions' => $questions,
            ];
        }

        return ['polls' => $out, 'top' => $top, 'at' => now()->format('H:i:s')];
    }

    // The numbers of ONE poll's results, shared by the single-poll results page and the global one so both always
    // agree: turnout, weights and, per question, the ranking by weighted total then raw votes.
    private function resultsData(CouncilNationalPoll $poll, int $top): array
    {
        $id = $poll->id;

        $votes = CouncilNationalPollVote::with('user')->where("poll_id",$id)->get();

        // one row per member who voted (member-level, not per app account)
        $votersCount = $votes->groupBy(fn($v) => $v->member_id ?: 'u' . $v->user_id)->count();

        //Each elector is entered one time
        $electorsCount = CouncilNationalPollPermission::where('poll_id',$id)->count();
        $totalWeight = (int) CouncilNationalPollPermission::where('poll_id',$id)->sum('vote_weight');
        $votedWeight = (int) $votes->unique(fn($v) => ($v->member_id ?: 'u' . $v->user_id) . '|' . $v->question_id)
            ->groupBy('question_id')->map(fn($g) => $g->sum('weight'))->max();

        // birth dates of the candidates picked from the member list (the tie-break: the older one wins)
        $candidateIds = $poll->questions->flatMap(fn($q) => $q->answers->pluck('member_id'))->filter()->unique()->values()->all();
        $births = [];
        if ($candidateIds) {
            foreach (DB::table('fpm_users')->whereIn('MemberId', $candidateIds)->pluck('BirthDate', 'MemberId') as $mid => $d) {
                // a missing or placeholder date (0000-00-00, 1900-...) means "unknown", never "the oldest"
                $d = $d ? substr((string) $d, 0, 10) : null;
                $births[(string) $mid] = ($d && $d > '1900-01-02') ? $d : null;
            }
        }

        // ranked results per question: weighted total first; on equal weight the older candidate wins
        $ranking = [];
        foreach ($poll->questions as $question) {
            $qVotes = $votes->where('question_id', $question->id);
            $castWeight = (int) $qVotes->sum('weight');

            $rows = $question->answers->map(function ($answer) use ($qVotes, $castWeight) {   // (ages: see $births above)
                $a = $qVotes->where('answer_id', $answer->id);
                $w = (int) $a->sum('weight');
                return [
                    'answer' => $answer->answer,
                    'id' => $answer->id,
                    'member_id' => $answer->member_id,
                    'weight' => $w,
                    'votes' => $a->count(),
                    'pct' => $castWeight ? round($w * 100 / $castWeight, 1) : 0,
                ];
            })->map(function ($r) use ($births) {
                $r['birth'] = $r['member_id'] ? ($births[(string) $r['member_id']] ?? null) : null;
                return $r;
            })->sort(function ($a, $b) {
                // weight first; when two are equal the OLDER candidate (earlier birth date) wins; candidates
                // with no known birth date go after those who have one, then by name so the order is stable
                if ($a['weight'] !== $b['weight']) return $b['weight'] <=> $a['weight'];
                if ($a['birth'] !== $b['birth']) {
                    if ($a['birth'] === null) return 1;
                    if ($b['birth'] === null) return -1;
                    return strcmp($a['birth'], $b['birth']);
                }
                return strcmp((string) $a['answer'], (string) $b['answer']);
            })->values();

            // Places follow the order above: two get the same place only when they are equal in weight AND age
            // cannot separate them (same birth date, or one/both unknown).
            $rank = 0; $prev = null;
            $rows = $rows->map(function ($r, $i) use (&$rank, &$prev) {
                $ageSeparates = $prev && $prev['weight'] === $r['weight'] && $prev['birth'] !== null && $r['birth'] !== null && $prev['birth'] !== $r['birth'];
                if (!$prev || $prev['weight'] !== $r['weight'] || $ageSeparates) { $rank = $i + 1; }
                $prev = $r;
                $r['rank'] = $rank;
                return $r;
            });
            // mark who WON a weight tie because he is older, so the page can say so
            $rows = $rows->map(function ($r, $i) use ($rows) {
                $next = $rows[$i + 1] ?? null;
                $prev = $rows[$i - 1] ?? null;
                $r['won_by_age'] = $r['weight'] > 0 && $next && $next['weight'] === $r['weight'] && $r['birth'] !== null && $next['birth'] !== null && $next['birth'] !== $r['birth'];
                $r['tied_with_older'] = $r['weight'] > 0 && $prev && $prev['weight'] === $r['weight'] && $prev['birth'] !== null && $r['birth'] !== null && $prev['birth'] !== $r['birth'];
                return $r;
            });

            // only a tie that age cannot settle (same place on both sides of the cut-off) needs a human decision
            $tieAtCutoff = $top > 0 && $rows->count() > $top && $rows[$top - 1]['rank'] === $rows[$top]['rank'] && $rows[$top]['weight'] > 0;

            $ranking[$question->id] = ['rows' => $rows, 'cast_weight' => $castWeight, 'tie_at_cutoff' => $tieAtCutoff];
        }

        $meetingStats = $poll->meeting ? $poll->meeting->stats() : null;

        return compact('poll','votes','votersCount','electorsCount','totalWeight','votedWeight','ranking','top','meetingStats');
    }

    // (There is deliberately NO "delete all votes" action any more: votes of an election or a poll are never
    // wiped from the CMS.)
}
