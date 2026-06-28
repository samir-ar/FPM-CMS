<?php


namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\V2\CentralCommitteeCoordinator;
use App\V2\ApplicationState;
use App\V2\District;
use App\V2\Region;
use App\V2\Committee;
use DataTables;

use Carbon\Carbon;

use App\V2\AppUser;
use App\V2\Candidate;

use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;

class CentralCommitteeCoordinatorController extends Controller
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
            $data = CentralCommitteeCoordinator::leftJoin('app_users', 'app_users.id', '=', 'central_committee_coordinators.candidate_id')
            ->select(['state','central_committee_coordinators.id as id','registerer_id','central_committee_coordinators.candidate_id','app_users.name','app_users.member_id',
            'central_committee_coordinators.committee_id',
            'central_committee_coordinators.phase_1',
            'central_committee_coordinators.phase_2',
            'central_committee_coordinators.phase_3',
            'central_committee_coordinators.phase_4',
            'central_committee_coordinators.phase_5',
            'central_committee_coordinators.phase_6',
            'central_committee_coordinators.phase_7',
            'central_committee_coordinators.created_at',
            'central_committee_coordinators.updated_at', 
            'popularization_no'])->get();

            return DataTables::of($data)
                ->addColumn('action', function($row){
                    return 
                        /* "<a href='".route('admin.candidate-get',$row->candidate_id)."'> <i class=\"fa fa-user mr-20\"></i> </a>". */
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.central-committee-coordinator.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })

                ->addColumn('registerer_id',function($row){
                    $candidate = AppUser::find($row->registerer_id);
                    if(!$candidate)return null;

                    return "<a href='".route('admin.candidate-get',$row->registerer_id)."'>$candidate->name ($candidate->member_id)<a/>";
                })
/* 
                ->addColumn('100days', function($row){

                    $date = Carbon::parse($row->created_at);
                    $now = Carbon::now();
                    $diff = $date->diffInDays($now);
                    
                    if($row->state === ApplicationState::$WAITING && $diff>100 && (!$row->phase_1 || !$row->phase_2 || !$row->phase_3 || !$row->phase_4 || !$row->phase_5 || !$row->phase_6 ||!$row->phase_7 ||!$row->phase_8)){
                        return "<b style='color:red;'> Yes </b>";
                    }
                }) */

                ->addColumn('committee',function($row){
                    
                    $committee = Committee::find($row->committee_id);
                    return "<span style='text-align:center;'>". ($committee)?$committee->name:'DELETED' ."</span>";
                })

                 ->addColumn('name',function($row){
                    return "<div style='width: 200px;text-align: center;'>".$row->name."</div>";
                }) 

                ->addColumn('phase_1', function($row){
                    $date = $row->phase_1?date('d/m/Y', strtotime($row->phase_1)):'';
                    return "
                    <div style='cursor:pointer;' id='".$row->id."-1' ondblclick='updateDate(\"".$row->id."-1\",\"central-committee-coordinator\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })

                ->addColumn('phase_2', function($row){
                    $date = $row->phase_2?date('d/m/Y', strtotime($row->phase_2)):'';
                    return "
                    <div style='cursor:pointer;' id='".$row->id."-2' ondblclick='updateDate(\"".$row->id."-2\",\"central-committee-coordinator\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_3', function($row){
                    $date = $row->phase_3?date('d/m/Y', strtotime($row->phase_3)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-3' ondblclick='updateDate(\"".$row->id."-3\",\"central-committee-coordinator\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_4', function($row){
                    $date = $row->phase_4?date('d/m/Y', strtotime($row->phase_4)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-4' ondblclick='updateDate(\"".$row->id."-4\",\"central-committee-coordinator\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_5', function($row){
                    $date = $row->phase_5?date('d/m/Y', strtotime($row->phase_5)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-5' ondblclick='updateDate(\"".$row->id."-5\",\"central-committee-coordinator\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_6', function($row){
                    $date = $row->phase_6?date('d/m/Y', strtotime($row->phase_6)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-6' ondblclick='updateDate(\"".$row->id."-6\",\"central-committee-coordinator\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_7', function($row){
                    $date = $row->phase_7?date('d/m/Y', strtotime($row->phase_7)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-7' ondblclick='updateDate(\"".$row->id."-7\",\"central-committee-coordinator\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })
                ->addColumn('phase_8', function($row){
                    $date = $row->phase_8?date('d/m/Y', strtotime($row->phase_8)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-8' ondblclick='updateDate(\"".$row->id."-8\",\"central-committee-coordinator\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })

                ->addColumn('popularization_no',function($row){
                    return  "
                        <input type='number' style='margin:-8px; background:transparent; border:0; height:100%; padding:8px;' id='candidatePopularizationNo_".$row->id."' ondblclick='UpdatePopularizationNo($row->id,\"/admin/central-committee-coordinator-update-popularization-number/\")' value='$row->popularization_no' readonly />
                            ";
                    })

                ->addColumn('state',function($row){
                
                    $options = "";
                    forEach(ApplicationState::$STATES as $key => $value){
                        $options.= "<option value='$value' ".(($row->state==$value)?" selected":"").">$key</option>";
                    }   
                    return  " <select id='applicationState_$row->id' onchange='updateState($row->id, \"central-committee-coordinator-update-application-state/\" )' class='form-control'>$options</select>";
                    })
                ->escapeColumns('100days')
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'منسق لجنة مركزية',
            'table_title' => '',
            'slug'		=> 'central_committee_coordinators',
            'custom_btn' => "<a href='" . route('admin.central-committee-coordinator.create') ."' class='btn btn-primary'>Add Candidate</a>",
            'headers'	=> [
                'id',
               /*  "Registerer", */
                "Candidate Id",
       
                'Name',
                'Committee',

                'VP لإقتراح SG إستلام ',
                'SG طلب تحضير التعمييم من ',
                'SG إستلام التعميم من قبل  ',
                'VP إلى SG إرسال التعميم من ',
                'على إصدار التعميم VP موافقة ال ',
                'VP إلى SG إرسال نهائي من',
                'وتعميم GB من SG إستلام',
                
                'رقم التعميم',
                'State',
                /* '100 Days', */
                'Action',
            ],

            'action' => route('admin.central-committee-coordinator.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],/* 
                ['data' => 'registerer_id', 'name' => 'registerer_id'], */
                ['data' =>  'member_id', 'name'=> 'member_id'],
                ['data' =>  'name', 'name'=> 'name'],
                ['data' =>  'committee', 'name'=> 'committee'],

                ['data' =>  'phase_1', 'name'=> 'phase_1'],
                ['data' =>  'phase_2', 'name'=> 'phase_2'],
                ['data' =>  'phase_3', 'name'=> 'phase_3'],
                ['data' =>  'phase_4', 'name'=> 'phase_4'],
                ['data' =>  'phase_5', 'name'=> 'phase_5'],
                ['data' =>  'phase_6', 'name'=> 'phase_6'],
                ['data' =>  'phase_7', 'name'=> 'phase_7'],                                               

                ['data' =>  'popularization_no', 'name'=> 'popularization_no'],
                ['data' =>  'state', 'name'=> 'state'],
                /* ['data' =>  '100days', 'name'=> '100days'], */
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false]
            ]),
        ]);
    }



    public function editDate($id,$phaseNumber, Request $request){
        $centralCommitteeCoordinator = CentralCommitteeCoordinator::find($id);
        $centralCommitteeCoordinator['phase_'.$phaseNumber] = Carbon::parse(Carbon::createFromFormat('d/m/Y', request('date')))->format('Y-m-d H:i:s.u0');
        $centralCommitteeCoordinator->save();
        return $request->all();
    }



    public function deleteDate($id,$phaseNumber, Request $request){
        $centralCommitteeCoordinator = CentralCommitteeCoordinator::find($id);
        $centralCommitteeCoordinator['phase_'.$phaseNumber] = null;
        $centralCommitteeCoordinator->save();
        return $request->all();
    }

    public function updatePopularizationNumber($id,Request $request){
        $centralCommitteeCoordinator = CentralCommitteeCoordinator::find((int)$id);
        
        if(!$centralCommitteeCoordinator) response()->json(false);
        $centralCommitteeCoordinator->popularization_no = request('popularizationNo');
        
        $centralCommitteeCoordinator->save();
        
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
            'pageTitle'		=> 'إضافة مرشح لمنصب منسق لجنة مركزية',
            'method'		=> 'post',
            'form_action'	=> route('admin.central-committee-coordinator.store'),
            'add_another_record' => true,

            'boxes' => [
                    [
                        'wrapper-class' => 'col-md-6',
                        'class' => 'box-default',
                        'box-header' => 'Info',
                        'form_fields' => [
                            $this->drawHtml('select-box', 'Select The Candidate', 'candidate', '', AppUser::all()->pluck('name-with-member-id','id'), '', 'col-md-12 required mb-10'),              
                            $this->drawHtml('select-box', 'Select A Committee', 'committee', '', Committee::all()->pluck('name','id'), '', 'col-md-12 required mb-10')
                        ],
                    ],
                     [
                        'wrapper-class' => 'col-md-6',
                        'class' => 'box-default',
                        'box-header' => 'New Committee',
                        'form_fields' => [
                            '<p class=" text-success"><b>Note:</b> If you did not find the committee you are looking for add it here and it will be attached to this candidate. Also it will be added to the committee list.</p>',
                            $this->drawHtml('small_text', 'Add a committee', 'addCommittee', '', "", '', 'col-md-12 mb-10')
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
             'addCommittee' => 'required_without:committee',
             'committee' => 'required_without:addCommittee'
        ]);

       $committee_id = request("committee");
         if(request("addCommittee")){
            $committee = new Committee();
            $committee->name = request("addCommittee");
            $committee->save();

            $committee_id = $committee->id;
        }


        $centralCommitteeCoordinator = new CentralCommitteeCoordinator();

        $centralCommitteeCoordinator->candidate_id = request('candidate');
        $centralCommitteeCoordinator->state = ApplicationState::$WAITING;
        $centralCommitteeCoordinator->committee_id = $committee_id;
        $centralCommitteeCoordinator->save();

        if(request('submitAnotherOne'))
           return redirect()->route('admin.central-committee-coordinator.create')->with('message', 'Candidate has been added successfully');
        
        return redirect()->route('admin.central-committee-coordinator.index')->with('message', 'Candidate has been added successfully');
    }


    public function updateState($id, Request $request){
        $centralCommitteeCoordinator = CentralCommitteeCoordinator::find($id);
        $centralCommitteeCoordinator->state = $request->state;
        $centralCommitteeCoordinator->save();
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
       $centralCommitteeCoordinator =  CentralCommitteeCoordinator::find($id);
       
       $centralCommitteeCoordinator->delete();

       return back()->with('message', 'The Row Has Been Deleted Successfully');
    }

}