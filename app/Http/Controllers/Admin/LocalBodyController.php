<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\V2\LocalBody;
use App\V2\District;
use App\V2\ApplicationState;
use App\V2\Region;
use App\V2\LocalBodyPost;
use DataTables;

use Carbon\Carbon;

use App\V2\Candidate;
use App\V2\AppUser;

use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;

class LocalBodyController extends Controller
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
        
        $data = LocalBody::leftJoin('app_users', 'app_users.id', '=', 'local_bodies.candidate_id')
        ->select(['state','local_bodies.id as id','region','district','local_bodies.registerer_id','local_bodies.candidate_id','app_users.name', 'app_users.member_id','local_bodies.district','local_bodies.phase_1','local_bodies.phase_2','local_bodies.phase_3','local_bodies.phase_4','local_bodies.phase_5','local_bodies.phase_6','local_bodies.phase_7'
        ,'local_bodies.phase_8'
        ,'local_bodies.post_id'
        ,'local_bodies.created_at','local_bodies.updated_at', 'popularization_no']);
        
        if(request()->query('district')){
            $data->where('district',request()->query('district'));
        }
            
            $data = $data->get();

            return DataTables::of($data)
                ->addColumn('action', function($row){
                    return 
                        "<a href='".route('admin.candidate-get',$row->candidate_id)."'> <i class=\"fa fa-user mr-20\"></i> </a>".
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.local-body.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })

                ->addColumn('registerer_id',function($row){
                    $candidate = AppUser::find($row->registerer_id);
                    return "<a href='".route('admin.candidate-get',$row->registerer_id)."'> $candidate->name ($candidate->member_id)<a/>";
                })

                ->addColumn('100days', function($row){
                    
                    $date = Carbon::parse($row->created_at);
                    $now = Carbon::now();
                    $diff = $date->diffInDays($now);
                    
                    if($diff>100 && (!$row->phase_1 || !$row->phase_2 || !$row->phase_3 || !$row->phase_4 || !$row->phase_5 || !$row->phase_6 ||!$row->phase_7 ||!$row->phase_8 ||!$row->phase_9||!$row->phase_10||!$row->phase_11||!$row->phase_12||!$row->phase_13||!$row->phase_14||!$row->phase_15||!$row->phase_16||!$row->phase_17||!$row->phase_18||!$row->phase_19||!$row->phase_20||!$row->phase_21)){
                        return "<b style='color:red;'> Yes </b>";
                    }
                })

                ->addColumn('name',function($row){
                    return "
                              <input type='text' style='margin:-8px; background:transparent; border:0; height:100%; padding:8px;' id='condidate_".$row->candidate_id."' ondblclick='UpdateName($row->candidate_id)' value='$row->name' readonly />
                            ";
                })

                ->addColumn('phase_1', function($row){
                    $date = $row->phase_1?date('d/m/Y', strtotime($row->phase_1)):'';
                    return "
                    <div style='cursor:pointer;' id='".$row->id."-1' ondblclick='updateDate(\"".$row->id."-1\",\"local-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })

                ->addColumn('phase_2', function($row){
                    $date = $row->phase_2?date('d/m/Y', strtotime($row->phase_2)):'';
                    return "
                    <div style='cursor:pointer;' id='".$row->id."-2' ondblclick='updateDate(\"".$row->id."-2\",\"local-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_3', function($row){
                    $date = $row->phase_3?date('d/m/Y', strtotime($row->phase_3)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-3' ondblclick='updateDate(\"".$row->id."-3\",\"local-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_4', function($row){
                    $date = $row->phase_4?date('d/m/Y', strtotime($row->phase_4)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-4' ondblclick='updateDate(\"".$row->id."-4\",\"local-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_5', function($row){
                    
                    $date = $row->phase_5?date('d/m/Y', strtotime($row->phase_5)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-5' ondblclick='updateDate(\"".$row->id."-5\",\"local-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_6', function($row){
                    $date = $row->phase_6?date('d/m/Y', strtotime($row->phase_6)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-6' ondblclick='updateDate(\"".$row->id."-6\",\"local-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_7', function($row){
                    $date = $row->phase_7?date('d/m/Y', strtotime($row->phase_7)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-7' ondblclick='updateDate(\"".$row->id."-7\",\"local-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })
                ->addColumn('phase_8', function($row){
                    $date = $row->phase_8?date('d/m/Y', strtotime($row->phase_8)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-8' ondblclick='updateDate(\"".$row->id."-8\",\"local-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })

                ->addColumn('popularization_no',function($row){
                    return  "
                        <input type='number' style='margin:-8px; background:transparent; border:0; height:100%; padding:8px;' id='candidatePopularizationNo_".$row->id."' ondblclick='UpdatePopularizationNo($row->id,\"/admin/local-body-update-popularization-number/\")' value='$row->popularization_no' readonly />
                            ";
                    })
                ->addColumn('state',function($row){
            
                    $options = "";
                    forEach(ApplicationState::$STATES as $key => $value){
                        $options.= "<option value='$value' ".(($row->state==$value)?" selected":"").">$key</option>";
                    }   
                    return  " <select id='applicationState_$row->id' onchange='updateState($row->id, \"local-body-update-application-state/\" )' class='form-control'>$options</select>";
                    })
                ->addColumn('post',function($row){
                    $post = LocalBodyPost::find($row->post_id);
                    
                    if($post){
                        $name= $post->name;
                    }else{
                        $name= "DELETED";
                    }
                    return  "<span>".
                        $name
                    ."</span";
                    })
                ->escapeColumns('100days')
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'عضو في هيئة محلية',
            'table_title' => '',
            'slug'		=> 'district_coordinators',
            'custom_btn' => "<a href='" . route('admin.local-body.create') ."' class='btn btn-primary'>Add Candidate</a>",
            'headers'	=> [
                'id',
                'Name',
                "المنصب",
                'قضاء',
                'بلدة',
                "Registerer",
                "Candidate Id",
                "إقتراح الهيئة",
                'موافقة القضاء',
                'موافقة القطاع',
                'إستلام أمانة السر',
                'VP إلى SG إرسال',
                "VP موافقة",
                'VP إلى SG إرسال نهائي',
                'وتعميم GB من SG إستلام',
                'رقم التعميم',
                'State',
                /* '100 Days', */
                'Action',
            ],

            'action' => route('admin.local-body.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'name', 'name'=> 'name'],
                ['data' =>  'post', 'name'=> 'post'],
                ['data' =>  'district', 'name'=> 'district'],
                ['data' =>  'region', 'name'=> 'region'],
                ['data' => 'registerer_id', 'name' => 'registerer_id'],
                ['data' =>  'member_id', 'name'=> 'member_id'],
                ['data' =>  'phase_1', 'name'=> 'phase_1'],
                ['data' =>  'phase_2', 'name'=> 'phase_2'],
                ['data' =>  'phase_3', 'name'=> 'phase_3'],
                ['data' =>  'phase_4', 'name'=> 'phase_4'],
                ['data' =>  'phase_5', 'name'=> 'phase_5'],
                ['data' =>  'phase_6', 'name'=> 'phase_6'],
                ['data' =>  'phase_7', 'name'=> 'phase_7'],
                ['data' =>  'phase_8', 'name'=> 'phase_8'],
        
                ['data' =>  'popularization_no', 'name'=> 'popularization_no'],
                ['data' =>  'state', 'name'=> 'state'],
               /*  ['data' =>  '100days', 'name'=> '100days'], */
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false]
            ]),

            'districts' => District::select(['name'])->get()
        ]);
    }



    public function editDate($id,$phaseNumber, Request $request){
        $localBody = LocalBody::find($id);
        $localBody['phase_'.$phaseNumber] = Carbon::parse(Carbon::createFromFormat('d/m/Y', request('date')))->format('Y-m-d H:i:s.u0');
        $localBody->save();
        return $request->all();
    }



    public function deleteDate($id,$phaseNumber, Request $request){
        $localBody = LocalBody::find($id);
        $localBody['phase_'.$phaseNumber] = null;
        $localBody->save();
        return $request->all();
    }

    public function updateState($id, Request $request){
        $localBody = LocalBody::find($id);
        $localBody->state = $request->state;
        $localBody->save();
        return response()->json(['message',"Succedd"]);
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
            'pageTitle'		=> 'إضافة مرشح لعضوية هيئة محلية',
            'method'		=> 'post',
            'form_action'	=> route('admin.local-body.store'),
            'add_another_record' => true,
            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Select Existing Person', 'candidate', '', AppUser::all()->pluck('name-with-member-id','id'), '', 'col-md-12 required mb-10'),           
                        $this->drawHtml('select-box', 'Registerer (Coordinator)', 'registerer_id', '', AppUser::all()->pluck('name-with-member-id','id'), '', 'col-md-12 required'),
                        $this->drawHtml('select-box', 'Post', 'post_id', '', LocalBodyPost::all()->pluck('name','id'), '', 'col-md-12 required'),
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Additional info',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'District', 'district_id', '',District::all()->pluck("name","id"), '', 'col-md-12 required') ,                       
                        $this->drawHtml('select-box', 'Region', 'region_id', '',[], '', 'col-md-12 required'),                        
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Additional info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Add a new Post', 'addPost', '', "", '', 'col-md-12 mb-10')                       
                    ],
                ]
            ],
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
            'registerer_id' => 'required',
            'district_id' => 'required',
            'region_id' => 'required',
            'post_id' => 'required_without:addPost',
        ]);

        $post_id = request('post_id');
        //add new post
        if(request('addPost')){
            $post = new LocalBodyPost();
            $post->name = request('addPost');
            $post->save();

            $post_id = $post->id;
        }

        $localBody = new LocalBody();
        $localBody->candidate_id = request('candidate');
        $localBody->district = District::find(request('district_id'))->name;
        $localBody->region = Region::find(request('region_id'))->name;
        $localBody->registerer_id = request('registerer_id');
        $localBody->post_id = $post_id;
        $localBody->state = ApplicationState::$WAITING;
        $localBody->save();

        if(request('submitAnotherOne'))
            return redirect()->route('admin.local-body.create')->with('message', 'Candidate has been added successfully');

        return redirect()->route('admin.local-body.index')->with('message', 'Candidate has been added successfully');
    }


    public function updatePopularizationNumber($id,Request $request){
        $localBody = LocalBody::find((int)$id);

        if(!$localBody) response()->json(false);
        $localBody->popularization_no = request('popularizationNo');

        $localBody->save();

        return response()->json(['message' =>"Succeed"]);
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
        $localBody =  LocalBody::find($id);
       
        $localBody->delete();
 
        return back()->with('message', 'The Row Has Been Deleted Successfully');
    }
}
