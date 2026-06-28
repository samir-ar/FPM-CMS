<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\V2\ApplicationState;
use DataTables;

use Carbon\Carbon;

use App\V2\ConsultingCommittee;
use App\V2\ConsultingCommitteeCommittee;
use App\V2\Post;
use App\V2\ConsultingCommitteePost;

use App\V2\AppUser;

use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;

class ConsultingCommitteeController extends Controller
{
    
    use FormTrait;
    use FileTrait;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if($request->ajax()) {
            $data = ConsultingCommittee::leftJoin('app_users', 'app_users.id', '=', 'consulting_committees.candidate_id')
            ->select(['state',
            'consulting_committees.id as id',
            'name',
            'app_users.member_id',
            "consulting_committees.candidate_id",
            "consulting_committees.post_id",
            "consulting_committees.committee_id",
            'consulting_committees.phase_1',
            'consulting_committees.phase_2',

            'popularization_no'])->get();

            return DataTables::of($data)
                ->addColumn('action', function($row){
                    
                    return 
                        "<a href='".route('admin.candidate-get',$row->candidate_id)."'> <i class=\"fa fa-user mr-20\"></i> </a>".
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.consulting-committee.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })


                ->addColumn('registerer_id',function($row){
                    $candidate = AppUser::find($row->registerer_id);
                    if($candidate)
                        return "<a href='".route('admin.candidate-get',$row->registerer_id)."'> $candidate->name ($candidate->member_id)<a/>";
                    return "No Registerer";
                })



                ->addColumn('name',function($row){
                    return '<div style="width: 140px;text-align: center">'.$row->name.'</div>';
                })



                ->addColumn('phase_1', function($row){
                    $date = $row->phase_1?date('d/m/Y', strtotime($row->phase_1)):'';
                    return "
                        <div style='cursor:pointer;' id='".$row->id."-1' ondblclick='updateDate(\"".$row->id."-1\",\"consulting-committee\")' class='input-group date'>
                            <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                        </div>
                            ";
                        })


                ->addColumn('phase_2', function($row){
                    $date = $row->phase_2?date('d/m/Y', strtotime($row->phase_2)):'';
                    return "
                    <div style='cursor:pointer;' id='".$row->id."-2' ondblclick='updateDate(\"".$row->id."-2\",\"consulting-committee\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
             

                ->addColumn('committee', function($row){
                
                    if($committee = ConsultingCommitteeCommittee::find($row->committee_id)){
                        return '<div style="width: 140px;text-align: center">'.$committee->name.'</div>';
                    }

                    return "";
                    
                })


                ->addColumn('post', function($row){
                
                    if($post = ConsultingCommitteePost::find($row->post_id)){
                        return '<div style="width: 140px;text-align: center">'.$post->name.'</div>';
                    }

                    return "";
                    
                })

            

                ->addColumn('popularization_no',function($row){
                    return  "
                        <input type='number' style='margin:-8px; background:transparent; border:0; height:100%; padding:8px;' id='candidatePopularizationNo_".$row->id."' ondblclick='UpdatePopularizationNo($row->id,\"/admin/consulting-committee-update-popularization-number/\")' value='$row->popularization_no' readonly />
                            ";
                    })
        
                ->addColumn('state',function($row){
                    $options = "";
                    forEach(ApplicationState::$STATES as $key => $value){
                        $options.= "<option value='$value' ".(($row->state==$value)?" selected":"").">$key</option>";
                    }   
                    return  " <select id='applicationState_$row->id' onchange='updateState($row->id, \"consulting-committee-update-application-state/\" )' class='form-control'>$options</select>";
                    })
                ->escapeColumns('100days')
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'عضو لجنة إستشارية',
            'table_title' => '',
            'slug'		=> 'district_coordinators',
            'custom_btn' => "<a href='" . route('admin.consulting-committee.create') ."' class='btn btn-primary'>Add Candidate</a>",
            'headers'	=> [
                'id',
                "Candidate Id",

                'Name',
                'Post',
                'Committee',

                'موافقة الرئيس',
                'موافقة نائب الرئيس',
                'رقم التعميم',

                'State',
                'Action',
            ],

            'action' => route('admin.consulting-committee.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'member_id', 'name'=> 'member_id'],

                ['data' =>  'name', 'name'=> 'name'],
                
                ['data' =>  'post', 'name'=> 'post'],
                ['data' =>  'committee', 'name'=> 'committee'],
                

                ['data' =>  'phase_1', 'name'=> 'phase_1'],
                ['data' =>  'phase_2', 'name'=> 'phase_2'],
                ['data' =>  'popularization_no', 'name'=> 'popularization_no'],
                

                ['data' =>  'state', 'name'=> 'state'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false]
            ]),
        ]);
    }



    public function editDate($id,$phaseNumber, Request $request){
        $consultingCommittee = ConsultingCommittee::find($id);
        $consultingCommittee['phase_'.$phaseNumber] = Carbon::parse(Carbon::createFromFormat('d/m/Y', request('date')))->format('Y-m-d H:i:s.u0');
        $consultingCommittee->save();
        return $request->all();
    }



    public function deleteDate($id,$phaseNumber, Request $request){
        $consultingCommittee = ConsultingCommittee::find($id);
        $consultingCommittee['phase_'.$phaseNumber] = null;
        $consultingCommittee->save();
        return $request->all();
    }

    public function updatePopularizationNumber($id,Request $request){
        $consultingCommittee = ConsultingCommittee::find((int)$id);

        if(!$consultingCommittee) response()->json(false);
        $consultingCommittee->popularization_no = request('popularizationNo');

        $consultingCommittee->save();

        return response()->json(['message' =>"Succeed"]);
    }

    /**
     * Show the form for creating a new resource. 
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'إضافة مرشخ لعضوية في لجنة إستشارية',
            'method'		=> 'post',
            'form_action'	=> route('admin.consulting-committee.store'),
            'add_another_record' => true,
            'boxes' => [
                [
                     'wrapper-class' => 'col-md-6',
                     'class' => 'box-default',
                     'box-header' => 'Additional info',
                     'form_fields' => [
                         $this->drawHtml('select-box', 'Select Existing Person', 'candidate', '', AppUser::all()->pluck('name-with-member-id','id'), '', 'col-md-12 required mb-10'),   
                         $this->drawHtml('select-box', 'Committee', 'committee', '',ConsultingCommitteeCommittee::all()->pluck("name","id"), '', 'col-md-12 required'),                
                         $this->drawHtml('select-box', 'Post', 'post', '',ConsultingCommitteePost::all()->pluck("name","id"), '', 'col-md-12 required')                
                     ],
                ],
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Do Not Finding What Are Looking For?',
                    'form_fields' => [
                        '<p class=" text-success"><b>Note:</b> If you did not find the committee or post you are looking for add it here and it will be attached to this candidate. Also it will be added to the committees/posts list.</p>',
                        $this->drawHtml('small_text', 'Add a new committee', 'addCommittee', '', "", '', 'col-md-12 mb-10'),
                        $this->drawHtml('small_text', 'Add a new Post', 'addPost', '', "", '', 'col-md-12 mb-10')
                    ],
                ]
            ]
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $this->validate($request, [
            'candidate' => 'required',
            'addCommittee' => 'required_without:committee',
            'committee' => 'required_without:addCommittee',
            'addPost' => 'required_without:post',
            'post' => 'required_without:addPost'
        ]);


        $committee = request('committee');
        if(request("addCommittee")){
            $_committee = new ConsultingCommitteeCommittee();
            $_committee->name = request("addCommittee");
            $_committee->save();
            $committee = $_committee->id;
        }
        

        $post = request('post');
        if(request("addPost")){
            $_post = new ConsultingCommitteePost();
            $_post->name = request("addPost");
            $_post->save();
            $post = $_post->id;
        }


        $consultingCommittee = new ConsultingCommittee();
        $consultingCommittee->candidate_id = request('candidate');
        $consultingCommittee->committee_id = $committee;
        $consultingCommittee->post_id = $post;

        $consultingCommittee->state = ApplicationState::$WAITING;
        $consultingCommittee->save();
        
        if(request('submitAnotherOne'))
            return redirect()->route('admin.consulting-committee.create')->with('message', 'Candidate has been added successfully');

        return redirect()->route('admin.consulting-committee.index')->with('message', 'Candidate has been added successfully');
    }


    public function updateState($id, Request $request){
        $consultingCommittee = ConsultingCommittee::find($id);
        $consultingCommittee->state = $request->state;
        $consultingCommittee->save();
        return response()->json(['message',"Succedd"]);
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
       $consultingCommittee =  ConsultingCommittee::find($id);
       $consultingCommittee->delete();
       return back()->with('message', 'The Row Has Been Deleted Successfully');
    }

}