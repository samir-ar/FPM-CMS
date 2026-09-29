<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use DataTables;
use App\V2\InternalElection;
use App\V2\ElectionState;
use App\V2\InternalElectionCandidate;
use App\V2\InternalElectionVote;
use App\V2\InternalElectionPermission;
use App\V2\AppUser;
use App\Imports\InternalElectionAllowedVotersImport;
use App\Exports\AllowedToVoteTemplateExport;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class InternalElectionController extends Controller
{
    use FormTrait;
    use FileTrait;
    public function index(Request $request){

        if($request->ajax()) {
            $data =  InternalElection::all();


            return DataTables::of($data)


            ->addColumn('title', function($row){
                return "<a href='".route('admin.internal-election-candidates.index',$row->id)."'>".
                   $row->title
                ."</a>";
            })

            ->addColumn('status', function($row){
                return "<button id='{$row->id}' class='publish btn ".(($row->is_active)?'btn-success':'')."'> publish </button>";
            })

            ->addColumn('closes', function($row){
                $value = $row->closes_at ? \Carbon\Carbon::parse($row->closes_at)->format('Y-m-d\TH:i') : '';
                return "<div style='display:flex; gap:6px; align-items:center;'>".
                    "<input type='datetime-local' class='form-control election-closes-at-input' style='width:200px' value='{$value}' data-id='{$row->id}'>".
                    "<button class='btn btn-primary btn-sm save-closes-at-row-btn' data-id='{$row->id}'>Save</button>".
                    "</div>";
            })

            ->addColumn('action', function($row){
                return "

                <a class='edit-link' href='" . route('admin.internal-election.edit', $row->id) . "'>".

                    "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.internal-election.destroy', $row->id) . "'>".
                    "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
            })

            ->rawColumns(['title','status','closes','action'])

            ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Internal Election'.((request()->query('type'))?" - ".ucfirst(request()->query('type')):""),
            'table_title' => '',
            'scripts' => ['/js/internal-election.js'],
            'slug'		=> 'Archives',
            'custom_btn' => "<a href='" . route('admin.internal-election.create') ."' class='btn btn-primary'>Add Election</a>",
            'custom_btn1' => "<a class='btn btn-danger' href='" . route('admin.internal-election-votes.reset') . "'>DELETE ALL VOTES</a>",
            'headers'	=> ['id','Title', 'Status', 'Closes at', 'Action'],
            'action' => route('admin.internal-election.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'title', 'name'=> 'title'],
                ['data' => 'status', 'name' => 'status', 'searchable' => false, 'sortable' => false],
                ['data' => 'closes', 'name' => 'closes', 'searchable' => false, 'sortable' => false],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }


    public function publish($id , Request $request){
        $internal = InternalElection::find($id);
        $internal->is_active = !$internal->is_active;
        $internal->save();
        return response()->json(['message',"Succedd"]);
    }

    public function updateClosesAt(Request $request, $id){
        $this->validate($request, [
            'closes_at' => 'nullable|date',
        ]);

        $internal = InternalElection::find($id);
        if(!$internal){
            return response()->json(['message' => 'Election not found'], 404);
        }

        $internal->closes_at = request('closes_at') ? Carbon::parse(request('closes_at'))->toDateTimeString() : null;
        $internal->save();

        return response()->json(['message' => 'Closing time updated']);
    }


    public function reset(Request $request){
        InternalElectionVote::truncate();
        return redirect()->route('admin.internal-election.index')->with('message', 'Election has been reset successfully');
    }

    public function downloadAllowedVotersTemplate(){
        return Excel::download(new AllowedToVoteTemplateExport(), 'allowed_to_vote_template.xlsx');
    }

    public function create(Request $request){

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add a new Election',
            'method'		=> 'post',
            'form_action'	=> route('admin.internal-election.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'عنوان الانتخابات', 'title', '', null, '', 'col-md-12 required right-to-left'),
                        $this->drawHtml('date-time-picker', 'موعد إقفال التصويت (اختياري)', 'closes_at', '', null, '', 'col-md-6'),
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-primary',
                    'box-header' => 'Permissions',
                    'form_fields' => [
                        $this->drawHtml('file', 'List of permitted voters', 'excel', '', null, '', 'col-md-12'),
                        "<p>The excel structure should be as following: | FPM ID | — <a href='" . route('admin.internal-election.import-allowed-voters-template') . "'>Download template</a>. Can also be added later from Edit.</p>",
                    ],
                ],
            ]
        ]);
    }

    public function store(Request $request){
        $this->validate($request, [
            'title' => 'required',
            'closes_at' => 'nullable|date',
            'excel' => 'nullable|mimes:xlsx,csv,xls',
        ]);

        $internal = new InternalElection();

        $internal->is_active = false;
        $internal->title = request('title');
        $internal->closes_at = request('closes_at') ? Carbon::parse(request('closes_at'))->toDateTimeString() : null;

        $internal->save();

        if ($request->excel) {
            Excel::import(new InternalElectionAllowedVotersImport($internal->id), $request->excel);
        }

        return redirect(route('admin.internal-election.index'))->with('message', 'Election has been created successfully');
    }

    public function edit($id, Request $request){
        $internal = InternalElection::find($id);
        if (!$internal) {
            return redirect(route('admin.internal-election.index'))->withErrors('Election not found');
        }

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Edit Election',
            'method'		=> 'update',
            'form_action'	=> route('admin.internal-election.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'عنوان الانتخابات', 'title', $internal->title, null, '', 'col-md-12 required right-to-left'),
                        $this->drawHtml('date-time-picker', 'موعد إقفال التصويت (اختياري)', 'closes_at', $internal->closes_at ? Carbon::parse($internal->closes_at)->format('Y-m-d\TH:i') : '', null, '', 'col-md-6'),
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-primary',
                    'box-header' => 'Permissions',
                    'form_fields' => [
                        $this->drawHtml('file', 'List of permitted voters', 'excel', '', null, '', 'col-md-12'),
                        "<p>Uploading a new file replaces the current list below. The excel structure should be as following: | FPM ID | — <a href='" . route('admin.internal-election.import-allowed-voters-template') . "'>Download template</a>.</p>",
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-primary',
                    'box-header' => 'Permitted',
                    'form_fields' => [
                        $this->getPermittedTable($id),
                    ],
                ],
            ]
        ]);
    }

    public function update($id, Request $request){
        $this->validate($request, [
            'title' => 'required',
            'closes_at' => 'nullable|date',
            'excel' => 'nullable|mimes:xlsx,csv,xls',
        ]);

        $internal = InternalElection::find($id);
        if (!$internal) {
            return redirect(route('admin.internal-election.index'))->withErrors('Election not found');
        }

        $internal->title = request('title');
        $internal->closes_at = request('closes_at') ? Carbon::parse(request('closes_at'))->toDateTimeString() : null;
        $internal->save();

        if ($request->excel) {
            // Full replace, not a merge — matches National Council Poll's
            // re-upload behavior.
            InternalElectionPermission::where('election_id', $internal->id)->delete();
            Excel::import(new InternalElectionAllowedVotersImport($internal->id), $request->excel);
        }

        return redirect(route('admin.internal-election.index'))->with('message', 'Election has been updated successfully');
    }

    private function getPermittedTable($electionId){
        // Joining directly to app_users on member_id fans one permission
        // row out into several displayed rows whenever a member has more
        // than one app_users account (a known, separate duplicate-accounts
        // issue) — look names up separately instead, one per member_id.
        $listPermissions = InternalElectionPermission::where('election_id', $electionId)->get();
        $names = AppUser::whereIn('member_id', $listPermissions->pluck('member_id'))
            ->get(['member_id', 'name'])
            ->unique('member_id')
            ->keyBy('member_id');

        $html = "<table class='table table-striped table-dark'>";
        $html .= "<thead><tr><th scope='col'>Member Id</th><th scope='col'>Name</th></tr></thead>";

        foreach ($listPermissions as $permission) {
            $html .= "<tr>";
            $html .= "<td>" . $permission->member_id . "</td>";
            $html .= "<td>" . ($names->get($permission->member_id)->name ?? '') . "</td>";
            $html .= "</tr>";
        }

        $html .= "</table>";
        return $html;
    }

    public function destroy($id){

        $election = InternalElection::find($id);
        $election->delete();
        return back()->with('message', 'Election Deleted Successfully');
    }


    public function export($id){
        $election = InternalElection::find($id);
        if(!$election){
            return redirect(route('admin.internal-election.index'))->withErrors( 'No election was found');
        }

        // Only the states this election actually has candidates in — a
        // universal ElectionState::all() would render an empty section for
        // every one of the ~30 states nationwide instead of just the
        // handful this election is actually contested in.
        $stateIds = \DB::table('internal_election_candidate_states')
            ->join('internal_election_candidates', 'internal_election_candidates.id', '=', 'internal_election_candidate_states.candidate_id')
            ->where('internal_election_candidates.election_id', $election->id)
            ->distinct()
            ->pluck('internal_election_candidate_states.election_state_id');

        $allStates = ElectionState::whereIn('id', $stateIds)->orderBy('name')->get();

        // Districts to actually display — everything by default, or just
        // the ones the admin checked in the filter box. Anything selected
        // that this election doesn't actually have candidates in is
        // ignored (intersect), so a stale/tampered query string can't
        // produce a bogus section.
        $selectedStateIds = request()->filled('states')
            ? array_map('intval', (array) request('states'))
            : $allStates->pluck('id')->all();

        $states = $allStates->whereIn('id', $selectedStateIds)->values();

        // Every candidate per state, including those with zero votes, so
        // the report is a complete tally rather than only listing whoever
        // received at least one vote.
        $results = $states->map(function($state) use ($election){
            $candidates = $election->candidates()
                ->whereHas('electionStates', function($q) use ($state){
                    $q->where('election_states.id', $state->id);
                })
                ->withCount(['internalElectionVotes as votes_count'])
                ->orderByDesc('votes_count')
                ->get();

            return [
                'state' => $state,
                'candidates' => $candidates,
                'voters' => $candidates->sum('votes_count'),
            ];
        });

        return view('election.report')->with(compact('election','results','allStates','selectedStateIds'));
    }
}
