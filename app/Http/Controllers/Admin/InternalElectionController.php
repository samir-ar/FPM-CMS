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
use App\Imports\AllowedToVoteImport;
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
            'custom_btn2' => "<a class='btn btn-success' href='" . route('admin.internal-election.import-allowed-voters-form') . "'>استيراد قائمة المسموح لهم بالتصويت</a>",
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

    public function importAllowedVotersForm(){
        return view('internal-election.import-allowed-voters')->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'استيراد قائمة المسموح لهم بالتصويت',
        ]);
    }

    public function downloadAllowedVotersTemplate(){
        return Excel::download(new AllowedToVoteTemplateExport(), 'allowed_to_vote_template.xlsx');
    }

    public function importAllowedVotersStore(Request $request){
        $this->validate($request, [
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        $import = new AllowedToVoteImport();
        Excel::import($import, $request->file('file'));

        return redirect()->route('admin.internal-election.index')
            ->with('message', "تم الاستيراد — {$import->matched} عضو تم تفعيل حق التصويت له"
                . ($import->notFound > 0 ? "، {$import->notFound} رقم انتساب غير موجود" : ""));
    }

    public function resetAllowedToVote(){
        \App\V2\FpmUser::query()->update(['Allowed_to_vote' => 0]);
        \App\V2\AppUser::query()->update(['Allowed_to_vote' => 0]);

        return redirect()->route('admin.internal-election.import-allowed-voters-form')
            ->with('message', 'تم إعادة تعيين جميع الأعضاء — لا أحد مسموح له بالتصويت الآن');
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
            ]
        ]);
    }



    public function store(Request $request){
        $this->validate($request, [
            'title' => 'required',
            'closes_at' => 'nullable|date',
        ]);

        $internal = new InternalElection();

        $internal->is_active = false;
        $internal->title = request('title');
        $internal->closes_at = request('closes_at') ? Carbon::parse(request('closes_at'))->toDateTimeString() : null;

        $internal->save();

        return redirect(route('admin.internal-election.index'))->with('message', 'Election has been created successfully');
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
