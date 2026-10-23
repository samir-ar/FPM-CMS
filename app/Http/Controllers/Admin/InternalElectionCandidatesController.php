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

            // Group by state (district), then by age within each state -
            // see the RowGroup setup in table_ajax.blade.php, which expects
            // rows to already arrive in the final grouped order (it doesn't
            // sort, it just inserts a header whenever the grouping column's
            // value changes between consecutive rows).
            $birthDates = \DB::table('fpm_users')
                ->whereIn('MemberId', $data->pluck('member_id')->filter())
                ->pluck('BirthDate', 'MemberId');

            // Every district's effective rank, as a small sequential integer
            // (0, 1, 2, ...) rather than the raw display_order - districts
            // with no display_order set yet (the common starting case) would
            // otherwise all collapse to the same fallback value and never
            // actually separate into their own groups. Ordered districts
            // come first (by display_order), unordered ones after
            // (alphabetically), matching states-order.blade.php's own list.
            $stateRanks = ElectionState::orderByRaw('display_order IS NULL, display_order asc')
                ->orderBy('name')
                ->pluck('id')
                ->flip(); // id => rank (0-based position in that order)

            // A candidate can technically belong to more than one district
            // (the pivot allows it, e.g. small adjacent districts merged
            // into one seat) - group by whichever one ranks lowest, so
            // grouping is always deterministic even then.
            $primaryState = function ($candidate) use ($stateRanks) {
                return $candidate->electionStates
                    ->sortBy(fn($s) => $stateRanks->get($s->id, PHP_INT_MAX))
                    ->first();
            };

            $data = $data->map(function ($candidate) use ($birthDates, $primaryState, $stateRanks) {
                $birth = $birthDates->get($candidate->member_id);
                $candidate->computed_age = $birth ? \Carbon\Carbon::parse($birth)->age : null;
                $state = $primaryState($candidate);
                $candidate->computed_state_name = $state->name ?? 'بدون دائرة';
                $rank = $state ? $stateRanks->get($state->id, PHP_INT_MAX) : PHP_INT_MAX;
                // Candidates with no age on file (manually-entered, no FPM
                // record) sort last within their district group, per the
                // confirmed call - 9999 is comfortably above any real age.
                $candidate->computed_sort_key = ($rank * 10000) + ($candidate->computed_age ?? 9999);
                return $candidate;
            })->sortBy('computed_sort_key')->values();

            return DataTables::of($data)


            ->addColumn('image', function($row){
                return "<img width='100' src='".$row->photo_url."'>";
            })


            ->addColumn('state', function($row){
                return $row->electionStates->pluck('name')->implode(', ');
            })

            ->addColumn('age', function($row){
                return $row->computed_age ?? '';
            })

            ->addColumn('state_group', function($row){
                return $row->computed_state_name;
            })

            ->addColumn('position', function($row){
                if (!$row->member_id) {
                    return '';
                }
                $fpmUser = FpmUser::where('MemberId', $row->member_id)->first(['LastUnitPosition', 'LastPositionUnitName']);
                if (!$fpmUser) {
                    return '';
                }

                // Both fields hold comma-separated lists that line up
                // index-for-index (position[i] belongs to unit[i]) - pair
                // them instead of showing "all positions (all units)" as
                // one undifferentiated block.
                $positions = array_values(array_filter(array_map('trim', explode(',', $fpmUser->LastUnitPosition ?? ''))));
                $units = array_values(array_filter(array_map('trim', explode(',', $fpmUser->LastPositionUnitName ?? ''))));

                $pairs = [];
                for ($i = 0; $i < max(count($positions), count($units)); $i++) {
                    $pos = $positions[$i] ?? '';
                    $unit = $units[$i] ?? '';
                    if ($pos && $unit) {
                        $pairs[] = $pos . ' (' . $unit . ')';
                    } elseif ($pos || $unit) {
                        $pairs[] = $pos ?: $unit;
                    }
                }

                return implode(', ', $pairs);
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


        $totalCandidates = InternalElection::where('id', $id)->first()->candidates()->count();

        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Internal Election'.((request()->query('type'))?" - ".ucfirst(request()->query('type')):""),
            'table_title' => 'Total Candidates: '.$totalCandidates,
            'scripts' => ['/js/internal-election.js'],
            'slug'		=> 'Archives',
            'generateInternalElectionReport' => true,
            'custom_btn' => "<a href='" . route('admin.internal-election-candidates.create', ['election_id' => $id]) ."' class='btn btn-primary'>Add candidate</a>",
            'custom_btn1' => "<a href='" . route('admin.internal-election.states-order') . "' class='btn btn-default'>Order Districts</a>",
            'headers'	=> ['id','Name','Image', 'State', 'Position', 'Age', 'Order','Action', ''],
            'action' => route('admin.internal-election.index'),
            // Grouped by district then age server-side (see the controller
            // above) - ordering is disabled client-side so an admin
            // clicking a header can't scramble the grouped order; 'state_group'
            // is hidden and exists purely as RowGroup's grouping key.
            'disableOrdering' => true,
            'rowGroupDataSrc' => 'state_group',
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'name', 'name'=> 'name'],
                ['data' => 'image', 'name'=> 'image', 'searchable' => false, 'sortable' => false],
                ['data' => 'state', 'name'=> 'state'],
                ['data' => 'position', 'name'=> 'position', 'searchable' => false, 'sortable' => false],
                ['data' => 'age', 'name'=> 'age', 'searchable' => false, 'sortable' => false],
                ['data' => 'order', 'name'=> 'order', 'searchable' => false, 'sortable' => false],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
                ['data' => 'state_group', 'name'=> 'state_group', 'visible' => false, 'searchable' => false, 'sortable' => false],
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

            if(!$this->districtMatchesAnyState($memberDistrict, $selectedStateNames)){
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

        // fpm_users.district is free-text and doesn't always match
        // election_states.name exactly (double spaces, hamza spelling like
        // بيروت الاولى vs بيروت  الأولى, or combined values like
        // المنية-الضنية / بعلبك - الهرمل covering more than one state) —
        // normalize whitespace/hamza and match by "contains" instead of
        // exact equality so these real-world variants still match. Shared
        // by the search endpoint and the store() validation so they can
        // never disagree about whether a member belongs to a state.
        private function normalizeDistrictName(string $s): string
        {
            $s = trim(preg_replace('/\s+/u', ' ', $s));
            return str_replace(['أ', 'إ', 'آ'], 'ا', $s);
        }

        private function districtMatchesAnyState(?string $memberDistrict, array $stateNames): bool
        {
            if (!$memberDistrict) {
                return false;
            }
            $normalizedDistrict = $this->normalizeDistrictName($memberDistrict);
            foreach ($stateNames as $name) {
                if (str_contains($normalizedDistrict, $this->normalizeDistrictName($name))) {
                    return true;
                }
            }
            return false;
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

            $normalizedStateNames = array_map(fn($n) => $this->normalizeDistrictName($n), $stateNames);

            $results = FpmUser::where(function($query) use ($q){
                    $query->where('MemberId', 'like', "%{$q}%")
                        ->orWhere('MobileNumber', 'like', "%{$q}%")
                        ->orWhere('UserFullName', 'like', "%{$q}%");
                })
                ->where(function ($query) use ($normalizedStateNames) {
                    foreach ($normalizedStateNames as $name) {
                        $query->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(TRIM(district), 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا') like ?",
                            ['%' . $name . '%']
                        );
                    }
                })
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
