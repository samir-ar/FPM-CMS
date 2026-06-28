<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\V2\DistrictCoordinator;
use App\V2\Candidate;

use App\V2\AppUser;

use App\V2\ApplicationState;
use App\Http\Controllers\Controller;


use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;

use Carbon\Carbon;
use DataTables;


class DistrictCoordinatorController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request){

        if($request->ajax()) {
            $data = DistrictCoordinator::leftJoin('app_users', 'app_users.id', '=', 'district_coordinators.candidate_id')
            ->select(['state', "name", 'app_users.member_id','district_coordinators.candidate_id', 'district_coordinators.id as id','district_coordinators.district','district_coordinators.phase_1','district_coordinators.district','district_coordinators.phase_2','district_coordinators.phase_3','district_coordinators.phase_4','district_coordinators.phase_5','district_coordinators.phase_6','district_coordinators.phase_7','district_coordinators.created_at','district_coordinators.updated_at', 'popularization_no'])->get();

            return DataTables::of($data)
                ->addColumn('action', function($row){
                    
                    return "<a href='".route('admin.candidate-get',$row->candidate_id)."'> <i class=\"fa fa-user mr-20\"></i> </a>".
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.district-coordinator.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->addColumn('100days', function($row){

                    $date = Carbon::parse($row->created_at);
                    $now = Carbon::now();
                    $diff = $date->diffInDays($now);
                    
                    if( $row->state === ApplicationState::$WAITING && $diff>100 && (!$row->phase_1 || !$row->phase_2 || !$row->phase_3 || !$row->phase_4 || !$row->phase_5 || !$row->phase_6 ||!$row->phase_7 )){
                        return "<b style='color:red;'> Yes </b>";
                    }
                })/* 
                ->addColumn('name',function($row){
                    return  "
                              <input type='text' style='margin:-8px; background:transparent; border:0; height:100%; padding:8px;' id='condidate_".$row->candidate_id."' ondblclick='UpdateName($row->candidate_id)' value='$row->name' readonly />
                            ";
                })  */
                ->addColumn('name',function($row){
                    return  "<div style='width: 200px;text-align: center;'>".$row->name."</div>";
                })
                ->addColumn('phase_1', function($row){
                    $date = $row->phase_1?date('d/m/Y', strtotime($row->phase_1)):'';
                    return "
         
                    <div style='cursor:pointer;' id='".$row->id."-1' ondblclick='updateDate(\"".$row->id."-1\",\"district-coordinator\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_2', function($row){
                    $date = $row->phase_2?date('d/m/Y', strtotime($row->phase_2)):'';
                    return "
                    <div style='cursor:pointer;' id='".$row->id."-2' ondblclick='updateDate(\"".$row->id."-2\",\"district-coordinator\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_3', function($row){
                    $date = $row->phase_3?date('d/m/Y', strtotime($row->phase_3)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-3' ondblclick='updateDate(\"".$row->id."-3\",\"district-coordinator\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_4', function($row){
                    $date = $row->phase_4?date('d/m/Y', strtotime($row->phase_4)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-4' ondblclick='updateDate(\"".$row->id."-4\",\"district-coordinator\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_5', function($row){
                    $date = $row->phase_5?date('d/m/Y', strtotime($row->phase_5)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-5' ondblclick='updateDate(\"".$row->id."-5\",\"district-coordinator\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_6', function($row){
                    $date = $row->phase_6?date('d/m/Y', strtotime($row->phase_6)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-6' ondblclick='updateDate(\"".$row->id."-6\",\"district-coordinator\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_7', function($row){
                    $date = $row->phase_7?date('d/m/Y', strtotime($row->phase_7)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-7' ondblclick='updateDate(\"".$row->id."-7\",\"district-coordinator\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })
                ->addColumn('popularization_no',function($row){
                    return  "
                        <input type='number' style='margin:-8px; background:transparent; border:0; height:100%; padding:8px;' id='candidatePopularizationNo_".$row->id."' ondblclick='UpdatePopularizationNo($row->id,\"/admin/district-coordinator-update-popularization-number/\")' value='$row->popularization_no' readonly />
                            ";
                    })
                ->addColumn('state',function($row){
                    
                    $options = "";
                    forEach(ApplicationState::$STATES as $key => $value){
                        $options.= "<option value='$value' ".(($row->state==$value)?" selected":"").">$key</option>";
                    }   
                    return  " <select id='applicationState_$row->id' onchange='updateState($row->id, \"district-coordinator-update-application-state/\" )' class='form-control'>$options</select>";
                    })
                ->escapeColumns('100days')
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'منسّق هيئة قضاء',
            'table_title' => '',
            'slug'		=> 'district_coordinators',
            'custom_btn' => "<a href='" . route('admin.district-coordinator.create') ."' class='btn btn-primary'>Add Candidate</a>",
            'headers'	=> [
                'id',
                "Candidate Id",
                'District',
                'Name',
                " SG لاقتراح VP استلام",
                'SG طلب تحضير التعميم من ',
                'وضع التواقيع + SG استلام التعميم من',
                'VP إلى SG إرسال التعميم من ',
                'VP إلى SG إرسال نهائي من',
                'على اصدار التعميم VP موافقى ال',
                'وتعميم GB من SG إستلام',
                'رقم التعميم',
                "State",
                /* '100 Days', */
                'Action',
            ],
            'action' => route('admin.district-coordinator.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'member_id', 'name'=> 'member_id'],
                ['data' =>  'district', 'name'=> 'district'],
                ['data' =>  'name', 'name'=> 'name'],
                ['data' =>  'phase_1', 'name'=> 'phase_1'],
                ['data' =>  'phase_2', 'name'=> 'phase_2'],
                ['data' =>  'phase_3', 'name'=> 'phase_3'],
                ['data' =>  'phase_4', 'name'=> 'phase_4'],
                ['data' =>  'phase_5', 'name'=> 'phase_5'],
                ['data' =>  'phase_6', 'name'=> 'phase_6'],
                ['data' =>  'phase_7', 'name'=> 'phase_7'],
                ['data' =>  'popularization_no', 'name'=> 'popularization_no'],
                ['data' =>  'state', 'name'=> 'state'],
               /*  ['data' =>  '100days', 'name'=> '100days'], */
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    public function updateState($id,Request $request){
        $districtCoordinator = DistrictCoordinator::find($id);
        $districtCoordinator->state = $request->state;
        $districtCoordinator->save();
        return response()->json(['message',"Succedd"]);
    }

    public function create(Request $request){
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'إضافة منسق قضاء',
            'method'		=> 'post',
            'form_action'	=> route('admin.district-coordinator.store'),
            'add_another_record' => true,

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Create New',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Select From Existing', 'candidate', '', AppUser::all()->pluck('name-with-member-id','id'), '', 'col-md-12 required mb-10'),
                        $this->drawHtml('select-box', 'District', 'district', '', 
                         array( 
                             'البترون'=> 'البترون', 
                              'البقاع الغربي' =>  'البقاع الغربي',
                              'الزهراني' => 'الوهراني' ,
                             'الشوف' => 'الشوف',
                             'الكورة' => 'الكورة',
                             'المتن' => 'المتن',
                             'المنية-الضنية' => 'المنية-الضنية',
                             'النبطية' => 'النبطية',
                             'بشري' => 'بشري',
                             'بعبدا' => 'بعبدا',
                             'بعلبك-الهرمل' => 'بعلبك-الهرمل',
                             'بنت جبيل-صور' => 'بنت جبيل-صور',
                             'بيروت الاولى' => 'بيروت الاولى',
                             'بيروت الثانية' => 'بيروت الثانية',
                             'جبيل' => 'جبيل',
                             'جزين' => 'جزين',
                             'راشيا' => 'راشيا',
                             'زحلة' => 'زحلة',
                             'زغرتا' => 'زغرتا',
                             'صيدا' => 'صيدا',
                             'طرابلس' => 'طرابلس',
                             'عاليه'=>'عاليه',
                             'عكار' => 'عكار',
                             'كسروان' => 'كسروان',
                             'مرجعيون-حاصبيا'=> 'مرجعيون-حاصبيا',
         
                             ), '', 'col-md-12 required')                        
                    ],
                ]
            ]
        ]);
    }

    public function edit(Request $request){
        
    }

    public function editDate($id,$phaseNumber, Request $request){
        $districtCoordinator = DistrictCoordinator::find($id);

        $districtCoordinator['phase_'.$phaseNumber] = Carbon::parse(Carbon::createFromFormat('d/m/Y', request('date')))->format('Y-m-d H:i:s.u0');

        $districtCoordinator->save();

        return $request->all();
    }

    public function deleteDate($id,$phaseNumber, Request $request){
        $districtCoordinator = DistrictCoordinator::find($id);

        $districtCoordinator['phase_'.$phaseNumber] = null;

        $districtCoordinator->save();

        return $request->all();
    }
    
    public function destroy($id){
        $dc = DistrictCoordinator::find($id);

        $dc->delete();
        return back()->with('message', 'The Row Has Been Deleted Successfully');
    }

    public function store(Request $request){
        $this->validate($request, [
            'candidate' => 'required',
            'district' => 'required'
        ]);

        $districtCoordinator = new DistrictCoordinator();
        $districtCoordinator->candidate_id = request('candidate');
        $districtCoordinator->district = request('district');
        $districtCoordinator->state = ApplicationState::$WAITING;
        $districtCoordinator->save();

        //This value come from the layout
        if(request('submitAnotherOne'))
            return redirect()->route('admin.district-coordinator.create')->with('message', 'Candidate has been added successfully');
        
        return redirect()->route('admin.district-coordinator.index')->with('message', 'Candidate has been added successfully');
    }

    public function updatePopularizationNumber($id,Request $request){
        $districtCoordinator = DistrictCoordinator::find((int)$id);

        if(!$districtCoordinator) response()->json(false);
        $districtCoordinator->popularization_no = request('popularizationNo');

        $districtCoordinator->save();

        return response()->json(['message' =>"Succeed"]);
    }
}