<?php


namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use DataTables;
use App\V2\InternalElection;
use App\V2\InternalElectionCandidate;
use App\V2\ElectionState;


class InternalElectionCandidatesController extends Controller
{
    use FormTrait;
    use FileTrait;
    public function index($id,Request $request){


        if($request->ajax()) {
            $data =  InternalElection::where('id',$id)->first()->candidates()->with('electionState')->get();



            return DataTables::of($data)


            ->addColumn('image', function($row){
                return "<img width='100' src='".$row->image_name."'>";
            })


            ->addColumn('state', function($row){
                return $row->electionState->name;
            })

            ->addColumn('action', function($row){
                return "

                <a class='edit
                -link' href='" . route('admin.internal-election.edit', $row->id) . "'>".

                    "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.internal-election-candidates.destroy', $row->id) . "'>".
                    "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
            })

            ->rawColumns(['image','action'])

            ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Internal Election'.((request()->query('type'))?" - ".ucfirst(request()->query('type')):""),
            'table_title' => '',
            'scripts' => ['/js/internal-election.js'],
            'slug'		=> 'Archives',
            'generateInternalElectionReport' => true,
            'custom_btn' => "<a href='" . route('admin.internal-election-candidates.create') ."' class='btn btn-primary'>Add candidate</a>",
            'headers'	=> ['id','Name','Image', 'State','Action'],
            'action' => route('admin.internal-election.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'name', 'name'=> 'name'],
                ['data' => 'image', 'name'=> 'image', 'searchable' => false, 'sortable' => false],
                ['data' => 'state', 'name'=> 'state'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    public function store(Request $request){
        $this->validate($request, [
            'image' => 'required',
            'name' => 'required',
            'state_id' => 'required',
        ]);

        $election = InternalElection::all()->last();

        $existingCandidate = $election->candidates()->where('name',request('name'))->first();


        if($existingCandidate){
            return redirect()->back()->withErrors(['msg' => 'Candidate with the same name already exist']);
        }

        $candidate = new InternalElectionCandidate();

            $candidate->name = request('name');
            $candidate->election_state_id = request('state_id');
            $candidate->election_id = $election->id;
            $candidate->image_name = $this->moveFile(request('image'),"images/candidates/");
            $candidate->save();


        if(request('submitAnotherOne'))
            return redirect()->route('admin.internal-election-candidates.create')->with('message', 'Candidate has been added successfully');
            return redirect()->route('admin.internal-election-candidates.index',$election->id)->with('message', 'Candidate has been added successfully');
        }

        public function destroy($id) {
            $candidate = InternalElectionCandidate::find($id);
            $this->removeFile($candidate->image_name);
            $candidate->delete();
            return back()->with('message', 'Candidate deleted successfully');
        }



    public function create(Request $request){
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add a new candidate',
            'method'		=> 'post',
            'add_another_record' => true,
            'form_action'	=> route('admin.internal-election-candidates.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Election State', 'state_id', '', ElectionState::all()->pluck('name','id'), '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Candidate Name', 'name', '',null, '', 'col-md-6 required'),
                        $this->drawHtml('file', 'Upload Candidate Image', 'image', '',"image/*" , '', 'col-md-12 required'),
                       ],
                ],
            ]
        ]);
    }
}
