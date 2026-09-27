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
use App\V2\FpmUser;


class InternalElectionCandidatesController extends Controller
{
    use FormTrait;
    use FileTrait;
    public function index($id,Request $request){


        if($request->ajax()) {
            $data =  InternalElection::where('id',$id)->first()->candidates()->with('electionStates')->get();



            return DataTables::of($data)


            ->addColumn('image', function($row){
                return "<img width='100' src='".$row->photo_url."'>";
            })


            ->addColumn('state', function($row){
                return $row->electionStates->pluck('name')->implode(', ');
            })

            ->addColumn('order', function($row){
                return "<input type='number' min='1' class='form-control candidate-order-input' style='width:80px' value='".($row->display_order ?? '')."' data-id='{$row->id}'>";
            })

            ->addColumn('action', function($row){
                return "

                <a class='edit
                -link' href='" . route('admin.internal-election.edit', $row->id) . "'>".

                    "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.internal-election-candidates.destroy', $row->id) . "'>".
                    "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
            })

            ->rawColumns(['image','order','action'])

            ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Internal Election'.((request()->query('type'))?" - ".ucfirst(request()->query('type')):""),
            'table_title' => '',
            'scripts' => ['/js/internal-election.js'],
            'slug'		=> 'Archives',
            'generateInternalElectionReport' => true,
            'custom_btn' => "<a href='" . route('admin.internal-election-candidates.create', ['election_id' => $id]) ."' class='btn btn-primary'>Add candidate</a>",
            'headers'	=> ['id','Name','Image', 'State', 'Order','Action'],
            'action' => route('admin.internal-election.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'name', 'name'=> 'name'],
                ['data' => 'image', 'name'=> 'image', 'searchable' => false, 'sortable' => false],
                ['data' => 'state', 'name'=> 'state'],
                ['data' => 'order', 'name'=> 'order', 'searchable' => false, 'sortable' => false],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    public function store(Request $request){
        $this->validate($request, [
            'election_id' => 'required|exists:internal_elections,id',
            'source' => 'required|in:fpm,manual',
            'member_id' => 'required_if:source,fpm|nullable|exists:fpm_users,MemberId',
            'first_name' => 'required',
            'father_name' => 'nullable',
            'family_name' => 'required',
            'display_order' => 'nullable|integer|min:1',
            'image' => 'nullable|image',
            'state_ids' => 'required|array',
            'state_ids.*' => 'exists:election_states,id',
        ]);

        $election = InternalElection::find(request('election_id'));
        $isFromFpm = request('source') === 'fpm';

        $existingCandidate = $election->candidates()
            ->where('first_name', request('first_name'))
            ->where('family_name', request('family_name'))
            ->first();

        if($existingCandidate){
            return redirect()->back()->withErrors(['msg' => 'Candidate with the same name already exist']);
        }

        if($isFromFpm){
            $existingByMember = $election->candidates()->where('member_id', request('member_id'))->first();
            if($existingByMember){
                return redirect()->back()->withErrors(['msg' => 'This FPM member is already added as a candidate']);
            }

            $memberDistrict = FpmUser::where('MemberId', request('member_id'))->value('district');
            $selectedStateNames = ElectionState::whereIn('id', request('state_ids'))->pluck('name')->toArray();

            if(!$memberDistrict || !in_array($memberDistrict, $selectedStateNames)){
                return redirect()->back()->withErrors(['msg' => 'This member\'s district ('.($memberDistrict ?: 'unknown').') does not match the selected Election State(s)']);
            }
        }

        $candidate = new InternalElectionCandidate();

            $candidate->first_name = request('first_name');
            $candidate->father_name = request('father_name');
            $candidate->family_name = request('family_name');
            $candidate->display_order = request('display_order');
            $candidate->election_id = $election->id;

            if($isFromFpm){
                $candidate->member_id = request('member_id');
                $candidate->image_name = null;
            } else {
                $candidate->member_id = null;
                $candidate->image_name = $request->hasFile('image')
                    ? $this->moveFile(request('image'),"images/candidates/")
                    : $this->defaultCandidateImage();
            }

            $candidate->save();
            $candidate->electionStates()->sync(request('state_ids'));


        if(request('submitAnotherOne'))
            return redirect()->route('admin.internal-election-candidates.create', ['election_id' => $election->id])->with('message', 'Candidate has been added successfully');
            return redirect()->route('admin.internal-election-candidates.index',$election->id)->with('message', 'Candidate has been added successfully');
        }

        public function searchFpmUsers(Request $request) {
            $q = trim((string) $request->get('q', ''));

            if (strlen($q) < 2) {
                return response()->json([]);
            }

            $stateIds = (array) $request->get('state_ids', []);
            $stateNames = $stateIds
                ? ElectionState::whereIn('id', $stateIds)->pluck('name')->toArray()
                : [];

            if (!$stateNames) {
                // No election state chosen yet (or none resolved) — nothing
                // to safely match a district against, so return no results
                // rather than showing members from every district.
                return response()->json([]);
            }

            $results = FpmUser::where(function($query) use ($q){
                    $query->where('MemberId', 'like', "%{$q}%")
                        ->orWhere('MobileNumber', 'like', "%{$q}%")
                        ->orWhere('UserFullName', 'like', "%{$q}%");
                })
                ->whereIn('district', $stateNames)
                ->limit(15)
                ->get(['MemberId', 'UserFullName', 'MobileNumber']);

            return response()->json($results->map(function($r){
                return [
                    'member_id' => $r->MemberId,
                    'name' => $r->UserFullName,
                    'mobile' => $r->MobileNumber,
                    'photo' => url('api/v2/member-photo/' . $r->MemberId),
                ];
            }));
        }

        public function updateOrder(Request $request, $id) {
            $this->validate($request, [
                'display_order' => 'nullable|integer|min:1',
            ]);

            $candidate = InternalElectionCandidate::find($id);
            if(!$candidate){
                return response()->json(['message' => 'Candidate not found'], 404);
            }

            $candidate->display_order = request('display_order');
            $candidate->save();

            return response()->json(['message' => 'Order updated']);
        }

        public function destroy($id) {
            $candidate = InternalElectionCandidate::find($id);
            if(!$candidate){
                return back()->with('message', 'Candidate was already deleted');
            }
            if($candidate->image_name){
                $this->removeFile($candidate->image_name);
            }
            $candidate->delete();
            return back()->with('message', 'Candidate deleted successfully');
        }

        private function defaultCandidateImage(){
            $fileName = 'default_avatar.png';
            $dir = public_path('images/candidates');
            $destination = $dir.'/'.$fileName;

            if(!is_dir($dir)){
                mkdir($dir, 0755, true);
            }

            if(!file_exists($destination)){
                copy(public_path('images/default_avatar.png'), $destination);
            }

            return $fileName;
        }



    public function create(Request $request){
        $election = InternalElection::find($request->query('election_id'));
        if(!$election){
            return redirect(route('admin.internal-election.index'))->withErrors('No election was found — open a specific election\'s candidates page and click "Add candidate" from there.');
        }

        $electionIdField = '<input type="hidden" name="election_id" value="'.$election->id.'">';

        $statesField = '<div class="form-group col-md-12 required">' .
            '<label>Election States (select one or more — a voter from any of them can vote for this candidate)</label>' .
            '<select name="state_ids[]" multiple class="form-control select2" style="width: 100%;">' .
            ElectionState::all()->map(function($s){
                return "<option value='{$s->id}'>{$s->name}</option>";
            })->implode('') .
            '</select></div>';

        $sourceField = <<<'HTML'
            <div class="form-group col-md-12">
                <label>Add candidate by</label><br>
                <label style="font-weight:normal; margin-right:20px;">
                    <input type="radio" name="source" value="fpm" id="source-fpm" checked> Searching FPM members
                </label>
                <label style="font-weight:normal;">
                    <input type="radio" name="source" value="manual" id="source-manual"> Entering manually
                </label>
            </div>

            <div class="form-group col-md-12" id="fpm-search-block">
                <label>Search by Member ID, Mobile Number, or Name</label>
                <div style="display:flex; gap:8px;">
                    <input type="text" id="fpm-search-input" class="form-control" placeholder="Type then press Search...">
                    <button type="button" id="fpm-search-btn" class="btn btn-primary" style="white-space:nowrap;">Search</button>
                </div>
                <div id="fpm-search-results" style="border:1px solid #ddd; max-height:220px; overflow:auto; margin-top:5px; display:none;"></div>
                <input type="hidden" name="member_id" id="member_id_input">
                <div id="fpm-selected-preview" style="margin-top:10px;"></div>
            </div>
            HTML;

        $nameFields =
            '<div class="form-group col-md-4 required">' .
                '<label>First Name</label>' .
                '<input type="text" name="first_name" id="first_name_input" class="form-control" value="'.old('first_name').'">' .
            '</div>' .
            '<div class="form-group col-md-4">' .
                '<label>Father Name</label>' .
                '<input type="text" name="father_name" id="father_name_input" class="form-control" value="'.old('father_name').'">' .
            '</div>' .
            '<div class="form-group col-md-4 required">' .
                '<label>Family Name</label>' .
                '<input type="text" name="family_name" id="family_name_input" class="form-control" value="'.old('family_name').'">' .
            '</div>' .
            '<div class="form-group col-md-4">' .
                '<label>Display Order (optional — e.g. 1, 2, 3. Lower shows first; candidates without one are listed after, alphabetically)</label>' .
                '<input type="number" name="display_order" min="1" class="form-control" value="'.old('display_order').'">' .
            '</div>';

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add a new candidate — '.$election->title,
            'method'		=> 'post',
            'add_another_record' => true,
            'form_action'	=> route('admin.internal-election-candidates.store'),
            'scripts' => ['/js/internal-election.js'],

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $electionIdField,
                        $statesField,
                        $sourceField,
                        $nameFields,
                        '<div id="manual-image-block">' .
                            $this->drawHtml('file', 'Upload Candidate Image (manual entry only — optional, a default photo is used if left empty)', 'image', '',"image/*" , '', 'col-md-12') .
                        '</div>',
                       ],
                ],
            ]
        ]);
    }
}
