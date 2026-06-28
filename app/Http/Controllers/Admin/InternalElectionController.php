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

            ->addColumn('action', function($row){
                return "

                <a class='edit-link' href='" . route('admin.internal-election.edit', $row->id) . "'>".

                    "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.internal-election.destroy', $row->id) . "'>".
                    "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
            })

            ->rawColumns(['title','action'])

            ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Internal Election'.((request()->query('type'))?" - ".ucfirst(request()->query('type')):""),
            'table_title' => '',
            'showPublishInternalElectionButton' => true,
            'row'=>InternalElection::all()->last(),
            'scripts' => ['/js/internal-election.js'],
            'slug'		=> 'Archives',
            'custom_btn' => "<a href='" . route('admin.internal-election.create') ."' class='btn btn-primary'>Add Election</a>",
            'headers'	=> ['id','Title', 'Action'],
            'action' => route('admin.internal-election.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'title', 'name'=> 'title'],
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


    public function reset(Request $request){
        InternalElectionVote::truncate();
        return redirect()->route('admin.internal-election.index')->with('message', 'Election has been reset successfully');
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
                    ],
                ],
            ]
        ]);
    }



    public function store(Request $request){
        $this->validate($request, [
            'title' => 'required',
        ]);

        $internal = new InternalElection();

        $internal->is_active = false;
        $internal->title = request('title');

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
        $states = ElectionState::all();
        if(!$election){
            return redirect(route('admin.internal-election.index'))->withErrors( 'No election was found');
        }


        $data = $election->candidates()->with('internalElectionVotes')->get();
        $votes = $election->votes()->get();

       /*  dd($election->votes()->selectRaw('`candidate_id`,SUM(`rank`) as `sum_rank`')
        ->groupBy('candidate_id')->orderBy('sum_rank','desc')->whereHas('candidate',function($q){ $q->where('election_state_id',2);})->get());
  */


       //dd($election->candidates()->where('election_state_id',2)->rank()->get());
       //dd($election->candidates()->where('election_state_id',2)->first()->internalElectionVotes()->select()->addSelect(\DB::raw('sum(`rank`) as `_rank`') )->groupBy('id')->orderBy('_rank','desc')->get());

      //the number of votes must be devided by 5 because
    /*  dd($election->votes()->whereHas('candidate',function($q){ $q->where('election_state_id',2); })->get()->count()/5);
        dd($election->votes()->count()/5);  */
    /*
        dd($data->last()->internalElectionVotes->sum('rank'));
        dd($data->last()->internalElectionVotes->where('rank',5)->count());
 */
        return view('election.report')->with(compact('votes','election','states'));
    }
}
