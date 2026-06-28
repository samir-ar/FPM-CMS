<?php


namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\V2\CentralCommittee;
use App\V2\District;
use App\V2\Region;
use App\V2\Post;
use App\V2\CentralCommitteePost;
use App\V2\ApplicationState;
use DataTables;

use Carbon\Carbon;

use App\V2\Candidate;
use App\V2\Committee;

use App\V2\AppUser;

use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;

class CentralCommitteeController extends Controller
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
            $data = CentralCommittee::leftJoin('app_users', 'app_users.id', '=', 'central_committees.candidate_id')
            ->select(['state',
            'app_users.name as name',
            'central_committees.id as id',
            'registerer_id',
            'app_users.member_id',
            'central_committees.committee_id',
            "central_committees.candidate_id",
            "central_committees.post_id",
            'central_committees.phase_1',
            'central_committees.phase_2',
            'central_committees.phase_3',
            'central_committees.phase_4',
            'central_committees.phase_5',
            'central_committees.phase_6',
            'central_committees.phase_7',
            'central_committees.phase_8',
            'central_committees.phase_9'
            ,'central_committees.created_at','central_committees.updated_at', 'popularization_no1', 'popularization_no2'])->get();

            return DataTables::of($data)
                ->addColumn('action', function($row){
                    
                    return 
                        "<a href='".route('admin.candidate-get',$row->candidate_id)."'> <i class=\"fa fa-user mr-20\"></i> </a>".
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.central-committee.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })

                ->addColumn('registerer_id',function($row){
                    $candidate = AppUser::find($row->registerer_id);
                    if($candidate)
                        return "<a href='".route('admin.candidate-get',$row->registerer_id)."'> $candidate->name ($candidate->member_id)<a/>";
                    return "No Registerer";
                })

                ->addColumn('100days', function($row){

                    $date = Carbon::parse($row->created_at);
                    $now = Carbon::now();
                    $diff = $date->diffInDays($now);
                    
                    if($row->state === ApplicationState::$WAITING && $diff>100 && (!$row->phase_1 || !$row->phase_2 || !$row->phase_3 || !$row->phase_4 || !$row->phase_5 || !$row->phase_6 ||!$row->phase_7 ||!$row->phase_8 ||!$row->phase_9||!$row->phase_10||!$row->phase_11||!$row->phase_12||!$row->phase_13||!$row->phase_14||!$row->phase_15||!$row->phase_16||!$row->phase_17||!$row->phase_18||!$row->phase_19||!$row->phase_20||!$row->phase_21)){
                        return "<b style='color:red;'> Yes </b>";
                    }
                })

                ->addColumn('name',function($row){
                    return '<div style="width: 140px;text-align: center">'.$row->name.'</div>';
                })


    
                ->addColumn('committee', function($row){
                
                    if($committee = Committee::find($row->committee_id)){
                        return '<div style="width: 140px;text-align: center">'.$committee->name.'</div>';
                    }

                    return "";
                    
                })


                ->addColumn('post', function($row){
                
                    if($post = Post::find($row->post_id)){
                        return '<div style="width: 140px;text-align: center">'.$post->name.'</div>';
                    }

                    return "";
                    
                })

                ->addColumn('phase_1', function($row){
                    $date = $row->phase_1?date('d/m/Y', strtotime($row->phase_1)):'';
                    return "
                    <div style='cursor:pointer;' id='".$row->id."-1' ondblclick='updateDate(\"".$row->id."-1\",\"central-committee\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })

                ->addColumn('phase_2', function($row){
                    $date = $row->phase_2?date('d/m/Y', strtotime($row->phase_2)):'';
                    return "
                    <div style='cursor:pointer;' id='".$row->id."-2' ondblclick='updateDate(\"".$row->id."-2\",\"central-committee\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_3', function($row){
                    $date = $row->phase_3?date('d/m/Y', strtotime($row->phase_3)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-3' ondblclick='updateDate(\"".$row->id."-3\",\"central-committee\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_4', function($row){
                    $date = $row->phase_4?date('d/m/Y', strtotime($row->phase_4)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-4' ondblclick='updateDate(\"".$row->id."-4\",\"central-committee\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_5', function($row){
                    $date = $row->phase_5?date('d/m/Y', strtotime($row->phase_5)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-5' ondblclick='updateDate(\"".$row->id."-5\",\"central-committee\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_6', function($row){
                    $date = $row->phase_6?date('d/m/Y', strtotime($row->phase_6)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-6' ondblclick='updateDate(\"".$row->id."-6\",\"central-committee\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_7', function($row){
                    $date = $row->phase_7?date('d/m/Y', strtotime($row->phase_7)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-7' ondblclick='updateDate(\"".$row->id."-7\",\"central-committee\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })
                ->addColumn('phase_8', function($row){
                    $date = $row->phase_8?date('d/m/Y', strtotime($row->phase_8)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-8' ondblclick='updateDate(\"".$row->id."-8\",\"central-committee\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })
                ->addColumn('phase_9', function($row){
                    $date = $row->phase_9?date('d/m/Y', strtotime($row->phase_9)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-9' ondblclick='updateDate(\"".$row->id."-9\",\"central-committee\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })

                ->addColumn('phase_10', function($row){
                    $date = $row->phase_10?date('d/m/Y', strtotime($row->phase_10)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-10' ondblclick='updateDate(\"".$row->id."-10\",\"central-committee\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })

                ->addColumn('popularization_no1',function($row){
                    return  "
                        <input type='number' style='margin:-8px; background:transparent; border:0; height:100%; padding:8px;' id='candidatePopularizationNo_".$row->id."_1' ondblclick='UpdatePopularizationNo($row->id,\"/admin/central-committee-update-popularization1-number/\", 1)' value='$row->popularization_no1' readonly />
                            ";
                    })

                ->addColumn('popularization_no2',function($row){
                    return  "
                        <input type='number' style='margin:-8px; background:transparent; border:0; height:100%; padding:8px;' id='candidatePopularizationNo_".$row->id."_2' ondblclick='UpdatePopularizationNo($row->id,\"/admin/central-committee-update-popularization2-number/\",2)' value='$row->popularization_no2' readonly />
                            ";
                    })

                ->addColumn('state',function($row){
                    $options = "";
                    forEach(ApplicationState::$STATES as $key => $value){
                        $options.= "<option value='$value' ".(($row->state==$value)?" selected":"").">$key</option>";
                    }   
                    return  " <select id='applicationState_$row->id' onchange='updateState($row->id, \"central-committee-update-application-state/\" )' class='form-control'>$options</select>";
                    })

                ->escapeColumns('100days')
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'عضو لحنة مركزية',
            'table_title' => '',
            'slug'		=> '',
            'custom_btn' => "<a href='" . route('admin.central-committee.create') ."' class='btn btn-primary'>Add Candidate</a>",
            'headers'	=> [
                'id',
                "Registerer",
                "Candidate Id",
   
                'Name',
                'Committee',
                'Post',

                'VP لإقتراح SG إستلام ',
                'SG طلب تحضير التعمييم من ',
                'SG إستلام التعميم من قبل  ',
                'VP إلى SG إرسال التعميم من ',
                'على إصدار التعميم VP موافقة ال ',
                'وتعميم GB من SG إستلام',
                'GB رقم التعميم',
                'VP موافقة',
                'على إصدار التعميم VP موافقة',
                'وتعميم GB من SG إستلام',
                'GB رقم التعميم ',
                'State',
                /* '100 Days', */
                'Action',
            ],

            'action' => route('admin.central-committee.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' => 'registerer_id', 'name' => 'registerer_id'],
                ['data' =>  'member_id', 'name'=> 'member_id'],

                ['data' =>  'name', 'name'=> 'name'],
                ['data' =>  'committee', 'name'=> 'committee'],
                ['data' =>  'post', 'name'=> 'post'],

                ['data' =>  'phase_1', 'name'=> 'phase_1'],
                ['data' =>  'phase_2', 'name'=> 'phase_2'],
                ['data' =>  'phase_3', 'name'=> 'phase_3'],
                ['data' =>  'phase_4', 'name'=> 'phase_4'],
                ['data' =>  'phase_5', 'name'=> 'phase_5'],
                ['data' =>  'phase_6', 'name'=> 'phase_6'],

                ['data' =>  'popularization_no1', 'name'=> 'popularization_no1'],

                ['data' =>  'phase_7', 'name'=> 'phase_7'],
                ['data' =>  'phase_8', 'name'=> 'phase_8'],                                                     
                ['data' =>  'phase_9', 'name'=> 'phase_9'],
                
                ['data' =>  'popularization_no2', 'name'=> 'popularization_no2'],

                ['data' =>  'state', 'name'=> 'state'],
               /*  ['data' =>  '100days', 'name'=> '100days'], */
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false]
            ]),
        ]);
    }



    public function editDate($id,$phaseNumber, Request $request){
        $centralCommittee = CentralCommittee::find($id);
        $centralCommittee['phase_'.$phaseNumber] = Carbon::parse(Carbon::createFromFormat('d/m/Y', request('date')))->format('Y-m-d H:i:s.u0');
        $centralCommittee->save();
        return $request->all();
    }



    public function deleteDate($id,$phaseNumber, Request $request){
        $centralCommittee = CentralCommittee::find($id);
        $centralCommittee['phase_'.$phaseNumber] = null;
        $centralCommittee->save();
        return $request->all();
    }

    public function updatePopularizationNumber1($id,Request $request){
        $centralCommittee = CentralCommittee::find((int)$id);

        if(!$centralCommittee) response()->json(false);

        //the index tell which popularization_no the user is trying to update
        $centralCommittee['popularization_no'."1"] = request('popularizationNo');

        $centralCommittee->save();

        return response()->json(['message' =>"Succeed"]);
    }
    public function updatePopularizationNumber2($id,Request $request){
        $centralCommittee = CentralCommittee::find((int)$id);

        if(!$centralCommittee) response()->json(false);

        //the index tell which popularization_no the user is trying to update
        $centralCommittee['popularization_no'."2"] = request('popularizationNo');

        $centralCommittee->save();

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
            'pageTitle'		=> 'Add A Central Committee Member Candidate',
            'method'		=> 'post',
            'form_action'	=> route('admin.central-committee.store'),
            'add_another_record' => true,
            'boxes' => [
                [
                     'wrapper-class' => 'col-md-6',
                     'class' => 'box-default',
                     'box-header' => 'Additional info',
                     'form_fields' => [
                         $this->drawHtml('select-box', 'Select Existing Person', 'candidate', '', AppUser::all()->pluck('name-with-member-id','id'), '', 'col-md-12 required mb-10'),           
                         $this->drawHtml('select-box', 'Registerer', 'registerer_id', '', AppUser::all()->pluck('name-with-member-id','id'), '', 'col-md-12 required'),
                         $this->drawHtml('checkbox', 'No Registerer', 'no_registerer', '',"", '', 'col-md-12').        
                         $this->drawHtml('select-box', 'Committee', 'committee', '',Committee::all()->pluck("name","id"), '', 'col-md-12 required'),     
                         $this->drawHtml('select-box', 'Post', 'post', '',CentralCommitteePost::all()->pluck("name","id"), '', 'col-md-12 required')        
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
            $_committee = new Committee();
            $_committee->name = request("addCommittee");
            $_committee->save();

            $committee = $_committee->id;
        }
        


        $post = request('post');
        
        if(request("addPost")){
            
            $_post = new CentralCommitteePost();
            $_post->name = request("addPost");
            $_post->save();

            $post = $_post->id;
        }
        

        $centralCommittee = new CentralCommittee();

        $centralCommittee->candidate_id = request("candidate");
        $centralCommittee->committee_id = $committee;
        $centralCommittee->post_id = $post;

        if(!request('no_registerer')){
            $centralCommittee->registerer_id = request('registerer_id');
        }

        $centralCommittee->state = ApplicationState::$WAITING;

        $centralCommittee->save();
        if(request('submitAnotherOne')){
            return redirect()->route('admin.central-committee.create')->with('message', 'Candidate has been added successfully');
        }
        return redirect()->route('admin.central-committee.index')->with('message', 'Candidate has been added successfully');
    }


    public function updateState($id, Request $request){
        $centralCommittee = CentralCommittee::find($id);
        $centralCommittee->state = $request->state;
        $centralCommittee->save();
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
       $centralCommittee =  CentralCommittee::find($id);
       $centralCommittee->delete();
       return back()->with('message', 'The Row Has Been Deleted Successfully');
    }

}