<?php


namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\V2\DistrictBody;
use App\V2\ApplicationState;
use DataTables;

use Carbon\Carbon;

use App\V2\DistrictPost;

use App\V2\AppUser;

use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;


class DistrictBodyController extends Controller
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
            $data = DistrictBody::leftJoin('app_users', 'app_users.id', '=', 'district_bodies.candidate_id')
            ->select(['state','district_bodies.id as id', 'member_id', 'district_bodies.registerer_id','district_bodies.candidate_id','name','district_bodies.district','district_bodies.phase_1','district_bodies.phase_2','district_bodies.district','district_bodies.phase_3','district_bodies.phase_4','district_bodies.phase_5','district_bodies.phase_6','district_bodies.phase_7'
            ,'district_bodies.phase_8'
            ,'district_bodies.phase_9'
            ,'district_bodies.phase_10'
            ,'district_bodies.phase_11'
            ,'district_bodies.phase_12'
            ,'district_bodies.phase_13'
            ,'district_bodies.phase_14'
            ,'district_bodies.phase_15'
            ,'district_bodies.phase_16'
            ,'district_bodies.phase_17'
            ,'district_bodies.phase_18'
            ,'district_bodies.phase_19'
            ,'district_bodies.phase_20'
            ,'district_bodies.phase_21'
            ,'district_bodies.post_id'
            ,'district_bodies.created_at','district_bodies.updated_at', 'popularization_no'])->get();

            return DataTables::of($data)
                ->addColumn('action', function($row){
                     return 
                        "<a href='".route('admin.candidate-get',$row->candidate_id)."'> <i class=\"fa fa-user mr-20\"></i> </a>".
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.district-body.destroy', $row->id) . "'>".
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
                    
                    if($row->state === ApplicationState::$WAITING && $diff>100 && (!$row->phase_1 || !$row->phase_2 || !$row->phase_3 || !$row->phase_4 || !$row->phase_5 || !$row->phase_6 ||!$row->phase_7 ||!$row->phase_8 ||!$row->phase_9||!$row->phase_10||!$row->phase_11||!$row->phase_12||!$row->phase_13||!$row->phase_14||!$row->phase_15||!$row->phase_16||!$row->phase_17||!$row->phase_18||!$row->phase_19||!$row->phase_20||!$row->phase_21)){
                        return "<b style='color:red;'> Yes </b>";
                    }
                })

                ->addColumn('name',function($row){
                    return "<div style='width: 200px;text-align: center;'>".$row->name."</div>";
                }) 
                
                /* 
                ->addColumn('name',function($row){
                    return "
                              <input type='text' style='margin:-8px; background:transparent; border:0; height:100%; padding:8px;' id='condidate_".$row->candidate_id."' ondblclick='UpdateName($row->candidate_id)' value='$row->name' readonly />
                            ";
                }) */

                ->addColumn('phase_1', function($row){
                    $date = $row->phase_1?date('d/m/Y', strtotime($row->phase_1)):'';
                    return "
                    <div style='cursor:pointer;' id='".$row->id."-1' ondblclick='updateDate(\"".$row->id."-1\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })

                ->addColumn('phase_2', function($row){
                    $date = $row->phase_2?date('d/m/Y', strtotime($row->phase_2)):'';
                    return "
                    <div style='cursor:pointer;' id='".$row->id."-2' ondblclick='updateDate(\"".$row->id."-2\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_3', function($row){
                    $date = $row->phase_3?date('d/m/Y', strtotime($row->phase_3)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-3' ondblclick='updateDate(\"".$row->id."-3\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_4', function($row){
                    $date = $row->phase_4?date('d/m/Y', strtotime($row->phase_4)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-4' ondblclick='updateDate(\"".$row->id."-4\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_5', function($row){
                    $date = $row->phase_5?date('d/m/Y', strtotime($row->phase_5)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-5' ondblclick='updateDate(\"".$row->id."-5\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_6', function($row){
                    $date = $row->phase_6?date('d/m/Y', strtotime($row->phase_6)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-6' ondblclick='updateDate(\"".$row->id."-6\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>
                            ";
                        })
                ->addColumn('phase_7', function($row){
                    $date = $row->phase_7?date('d/m/Y', strtotime($row->phase_7)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-7' ondblclick='updateDate(\"".$row->id."-7\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })
                ->addColumn('phase_8', function($row){
                    $date = $row->phase_8?date('d/m/Y', strtotime($row->phase_8)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-8' ondblclick='updateDate(\"".$row->id."-8\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })
                ->addColumn('phase_9', function($row){
                    $date = $row->phase_9?date('d/m/Y', strtotime($row->phase_9)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-9' ondblclick='updateDate(\"".$row->id."-9\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })

                ->addColumn('phase_10', function($row){
                    $date = $row->phase_10?date('d/m/Y', strtotime($row->phase_10)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-10' ondblclick='updateDate(\"".$row->id."-10\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })

                ->addColumn('phase_11', function($row){
                    $date = $row->phase_11?date('d/m/Y', strtotime($row->phase_11)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-11' ondblclick='updateDate(\"".$row->id."-11\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })
        

                ->addColumn('phase_12', function($row){
                    $date = $row->phase_12?date('d/m/Y', strtotime($row->phase_12)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-12' ondblclick='updateDate(\"".$row->id."-12\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })


                ->addColumn('phase_13', function($row){
                    $date = $row->phase_13?date('d/m/Y', strtotime($row->phase_7)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-13' ondblclick='updateDate(\"".$row->id."-13\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })


                ->addColumn('phase_14', function($row){
                    $date = $row->phase_14?date('d/m/Y', strtotime($row->phase_14)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-14' ondblclick='updateDate(\"".$row->id."-14\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })
                
                ->addColumn('phase_15', function($row){
                    $date = $row->phase_15?date('d/m/Y', strtotime($row->phase_15)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-15' ondblclick='updateDate(\"".$row->id."-15\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })
                
                ->addColumn('phase_16', function($row){
                    $date = $row->phase_16?date('d/m/Y', strtotime($row->phase_16)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-16' ondblclick='updateDate(\"".$row->id."-16\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })
                        
                ->addColumn('phase_17', function($row){
                    $date = $row->phase_17?date('d/m/Y', strtotime($row->phase_17)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-17' ondblclick='updateDate(\"".$row->id."-17\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })

                ->addColumn('phase_18', function($row){
                    $date = $row->phase_18?date('d/m/Y', strtotime($row->phase_18)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-18' ondblclick='updateDate(\"".$row->id."-18\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })

                ->addColumn('phase_19', function($row){
                    $date = $row->phase_19?date('d/m/Y', strtotime($row->phase_19)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-19' ondblclick='updateDate(\"".$row->id."-19\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })
                
                ->addColumn('phase_20', function($row){
                    $date = $row->phase_20?date('d/m/Y', strtotime($row->phase_20)):'';

                    return "
                    <div style='cursor:pointer;' id='".$row->id."-20' ondblclick='updateDate(\"".$row->id."-20\",\"district-body\")' class='input-group date'>
                        <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                    </div>";
                        })
                        
                ->addColumn('phase_21', function($row){
                    $date = $row->phase_21?date('d/m/Y', strtotime($row->phase_21)):'';

                    return "
                        <div style='cursor:pointer;' id='".$row->id."-21' ondblclick='updateDate(\"".$row->id."-21\",\"district-body\")' class='input-group date'>
                            <input type='text' style='width:90px;border:0;background:transparent;' class='form-control' value='$date' readonly>
                        </div>";
                        })
                ->addColumn('popularization_no',function($row){
                    return  "
                        <input type='number' style='margin:-8px; background:transparent; border:0; height:100%; padding:8px;' id='candidatePopularizationNo_".$row->id."' ondblclick='UpdatePopularizationNo($row->id,\"/admin/district-body-update-popularization-number/\")' value='$row->popularization_no' readonly />
                            ";
                    })
                ->addColumn('post',function($row){
                    $post = DistrictPost::find($row->post_id);
                    
                    if($post){
                        $name = $post->name;
                    }else{
                        $name = "DELETED";
                    }
                    return  "<span style='width:100%; text-align:center;'>".
                                    $name
                            ."</span>";
                })
                ->addColumn('state',function($row){
                
                    $options = "";
                    forEach(ApplicationState::$STATES as $key => $value){
                        $options.= "<option value='$value' ".(($row->state==$value)?" selected":"").">$key</option>";
                    }   
                    return  " <select id='applicationState_$row->id' onchange='updateState($row->id, \"district-body-update-application-state/\" )' class='form-control'>$options</select>";
                    })
                ->escapeColumns('100days')
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'عضو بهيئة قضاء',
            'table_title' => '',
            'slug'		=> 'district_coordinators',
            'custom_btn' => "<a href='" . route('admin.district-body.create') ."' class='btn btn-primary'>Add Candidate</a>",
            'headers'	=> [
                'id',
                "Registerer",
                "Candidate Id",
                'District',
                'Name',
                "المنصب",
                "إقتراح هيئة القضاء",
                'استلام أمانة السر',
                'VP إلى SG إرسال ',
                "'موافقة اللجان SGطلب ال",
                "العلاقات العامة",
                "امانة السر",
                "لجنة المال",
                "الموارد البشرية",
                "ماكينة انتخابية",
                "معلوماتية",
                "انتشار",
                "نشاطات ولوجستي",
                "شؤون مرأة",
                "شباب ورياضة",
                "بلديات",
                "ادارة ومراجعات",
                "اعلام",
                "VP ارسال الى",
                'VP إلى SG إرسال نهائي من',
                "VP موافقة",
                'وتعميم GB من SG إستلام',
                'رقم التعميم',
                'State',
               /*  '100 Days', */
                'Action',
            ],

            'action' => route('admin.district-body.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' => 'registerer_id', 'name' => 'registerer_id'],
                ['data' =>  'member_id', 'name'=> 'member_id'],
                ['data' =>  'district', 'name'=> 'district'],
                ['data' =>  'name', 'name'=> 'name'],
                ['data' =>  'post', 'name'=> 'post'],
                ['data' =>  'phase_1', 'name'=> 'phase_1'],
                ['data' =>  'phase_2', 'name'=> 'phase_2'],
                ['data' =>  'phase_3', 'name'=> 'phase_3'],
                ['data' =>  'phase_4', 'name'=> 'phase_4'],
                ['data' =>  'phase_5', 'name'=> 'phase_5'],
                ['data' =>  'phase_6', 'name'=> 'phase_6'],
                ['data' =>  'phase_7', 'name'=> 'phase_7'],
                ['data' =>  'phase_8', 'name'=> 'phase_8'],                                                     
                ['data' =>  'phase_9', 'name'=> 'phase_9'],
                ['data' =>  'phase_10', 'name'=> 'phase_10'],
                ['data' =>  'phase_11', 'name'=> 'phase_11'],
                ['data' =>  'phase_12', 'name'=> 'phase_12'],
                ['data' =>  'phase_13', 'name'=> 'phase_13'],
                ['data' =>  'phase_14', 'name'=> 'phase_14'],
                ['data' =>  'phase_15', 'name'=> 'phase_15'],
                ['data' =>  'phase_16', 'name'=> 'phase_16'],
                ['data' =>  'phase_17', 'name'=> 'phase_17'],
                ['data' =>  'phase_18', 'name'=> 'phase_18'],
                ['data' =>  'phase_19', 'name'=> 'phase_19'],
                ['data' =>  'phase_20', 'name'=> 'phase_20'],
                ['data' =>  'phase_21', 'name'=> 'phase_21'],
                ['data' =>  'popularization_no', 'name'=> 'popularization_no'],
                ['data' =>  'state', 'name'=> 'state'],
                /* ['data' =>  '100days', 'name'=> '100days'], */
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false]
            ]),
        ]);
    }


    public function editDate($id,$phaseNumber, Request $request){
        $districtBody = DistrictBody::find($id);
        $districtBody['phase_'.$phaseNumber] = Carbon::parse(Carbon::createFromFormat('d/m/Y', request('date')))->format('Y-m-d H:i:s.u0');
        $districtBody->save();
        return $request->all();
    }


    public function deleteDate($id,$phaseNumber, Request $request){
        $districtBody = DistrictBody::find($id);
        $districtBody['phase_'.$phaseNumber] = null;
        $districtBody->save();
        return $request->all();
    }

    public function updatePopularizationNumber($id,Request $request){
        $districtBody = DistrictBody::find((int)$id);

        if(!$districtBody) response()->json(false);
        $districtBody->popularization_no = request('popularizationNo');

        $districtBody->save();

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
            'pageTitle'		=> 'إضافة مرشح لعضوية هيئة قضاء',
            'method'		=> 'post',
            'form_action'	=> route('admin.district-body.store'),
            'add_another_record' => true,
            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Select Existing Person', 'candidate', '', AppUser::all()->pluck('name-with-member-id','id'), '', 'col-md-12 required mb-10'),           
                        $this->drawHtml('select-box', 'Registerer (District Coordinator)', 'registerer_id', '', AppUser::all()->pluck('name-with-member-id','id'), '', 'col-md-12 required'),
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
                            ), '', 'col-md-12 required'),   
                        $this->drawHtml('select-box', 'Post', 'post_id', '', DistrictPost::all()->pluck('name','id'), '', 'col-md-12 required'),
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
            'district' => 'required',
            'post_id' => 'required',
            'addPost' => 'required_without:post_id',
        ]);

        $post = request('post_id');
        if(request('addPost')){
            $districtPost = new DistrictPost();
            $districtPost->name = request('addPost');
            $districtPost->save();

            $post = $districtPost->id;
        }

        $districtBody = new DistrictBody();
        $districtBody->candidate_id = request('candidate');
        $districtBody->district = request('district');
        $districtBody->post_id = $post;
        $districtBody->registerer_id = request('registerer_id');
        $districtBody->state = ApplicationState::$WAITING;
        $districtBody->save();

        if(request('submitAnotherOne'))
            return redirect()->route('admin.district-body.create')->with('message', 'Candidate has been added successfully');
        
        return redirect()->route('admin.district-body.index')->with('message', 'Candidate has been added successfully');
    }


    public function updateState($id, Request $request){
        $districtBody = DistrictBody::find($id);
        $districtBody->state = $request->state;
        $districtBody->save();
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
       $districtBody =  DistrictBody::find($id);
       
       $districtBody->delete();

       return back()->with('message', 'The Row Has Been Deleted Successfully');
    }

}