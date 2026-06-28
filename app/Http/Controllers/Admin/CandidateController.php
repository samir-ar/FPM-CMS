<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\V2\Candidate;
use App\V2\DistrictCoordinator;
use App\V2\CentralCommitteeCoordinator;
use App\V2\CentralCommittee;
use App\V2\LocalBody;
use App\V2\DistrictBody;
use App\V2\AppUser;

class CandidateController extends Controller
{
    public function updateName($id, Request $request){
        $candidate = Candidate::find($id);

        $candidate->name = request("name");

        $result = $candidate->save();
        
        if($result){
            return response()->json(["message" => "success"],200);
        }
        return response()->json(["message" => "failed"],500);
    }

    public function getCandidate(AppUser $candidate){
        
        //People redisterd by this person
        //--> look for all people has in the table this person as registered by 

        //District Coordinator (Last one)

        $districtCoordinatorApplication = null;
        if($districtCoordinator = $candidate->districtCoordinatorCandidates){
            $districtCoordinatorApplication = $districtCoordinator->sortByDesc('create_at')->first();
        }

        $districtBodyApplication = null;
        if($districtBody=$candidate->districtBodiesCandidates){
            $districtBodyApplication = $districtBody->sortByDesc('create_at')->first();
        }    
        
        $localBodyApplication=null;
        if($localBody = $candidate->localBodiesCandidates){
            $localBodyApplication = $localBody->sortByDesc('create_at')->first();
        }
        
        $centralCommitteeCoordinatorApplication = null;
        if($centralCommitteeCoordinator = $candidate->centralCommitteeCoordinator){
            $centralCommitteeCoordinatorApplication = $centralCommitteeCoordinator->sortByDesc('create_at')->first();
        }


        $centralCommitteeApplication = null;
        if($centralCommittee = $candidate->centralCommitteesCandidates){
            $centralCommitteeApplication = $centralCommittee->sortByDesc('create_at')->first();
        }
        

        $consultingCommitteeApplication = null;
        if($consultingCommittee = $candidate->consultingCommitteeCandidates){
            $consultingCommitteeApplication = $consultingCommittee->sortByDesc('create_at')->first();
        }
        

        $html ="";

        if($consultingCommitteeApplication){
            $html .=      "<div class='row'>
            <div class='col-md-3'>
                Consulting Committee:
                </div>
                <div class='col-md-9'>
                    <p>$consultingCommitteeApplication->state</p>
                </div>
            </div>
            ";
        }

        if($districtCoordinatorApplication){
            $html .=      "<div class='row'>
            <div class='col-md-3'>
                District Coordinator (".$districtCoordinatorApplication->district."):
            </div>
            <div class='col-md-9'>
                <p>$districtCoordinatorApplication->state</p>
            </div>
        </div>
        ";
        }
      
        if($districtBodyApplication){
            $html .=      "<div class='row'>
            <div class='col-md-3'>
            District Body Memeber (".$districtBodyApplication->district."):
            </div>
            <div class='col-md-9'>
            <p>$districtBodyApplication->state</p>
            </div>
            </div>
            ";
        }

        if($localBodyApplication){
            $html .=      "<div class='row'>
            <div class='col-md-3'>
                Local Body Memeber (".$localBodyApplication->district."-".$localBodyApplication->region."):
            </div>
            <div class='col-md-9'>
                <p>$localBodyApplication->state</p>
            </div>
        </div>
        ";
        }  

        if($centralCommitteeCoordinatorApplication){
            $html .=      "<div class='row'>
                            <div class='col-md-3'>
                                Local Body Memeber (".$centralCommitteeCoordinatorApplication->district."-".$centralCommitteeCoordinatorApplication->region."):
                            </div>
                            <div class='col-md-9'>
                                <p>$centralCommitteeCoordinatorApplication->state</p>
                            </div>
                        </div>
        ";
        }

        $candidatesHtml = '';

        //Find Candidates

        //District Coordinator
       /*  $districtCoordinatorCandidates = DistrictCoordinator::where("registerer_id",$candidate->id)->get()->map(function($c){
            return "
                <tr>
                    <td>
                        <a href='/admin/get-candidate/".$c->candidate->id."'>".$c->candidate->name."</a>
                    </td>
                    <td>
                        ".$c->candidate->id."
                    </td>
                    <td>
                        $c->member_id
                    </td>
                    <td>
                        $c->phase_1
                    </td>
                    <td>
                        $c->phase_2
                    </td>
                    <td>
                        $c->phase_3
                    </td>
                    <td>
                        $c->phase_4
                    </td>
                    <td>
                        $c->phase_5
                    </td>
                    <td>
                        $c->phase_6
                    </td>
                    <td>
                        $c->phase_7
                    </td>
                    <td>
                        $c->popularization_no
                    </td>
                    <td>
                        $c->state
                    </td>
                </tr>
            ";
        });
        if($districtBodyCandidates){
            $candidatesHtml .= "<table class='table table-striped>
            <thead>
                <tr role='row'>
                    <th>id</th>
                    <th >Member ID</th>
                    <th >Name</th>
                    <th> SG لاقتراح VP استلام</th>
                    <th>SG طلب تحضير التعميم من </th>
                    <th>وضع التواقيع + SG استلام التعميم من</th>
                    <th >VP إلى SG إرسال التعميم من </th>
                    <th>VP إلى SG إرسال نهائي من</th>
                    <th>على اصدار التعميم VP موافقى ال</th>
                    <th>وتعميم GB من SG إستلام</th>
                    <th>رقم التعميم</th>
                    <th>State</th>
                </tr>
            </thead>
            <tbody>
                ".join(" ",$districtBodyCandidates)."
            </tbody>
            </br>
            ";
        }
 */

        $districtBodyCandidates = DistrictBody::where("registerer_id",$candidate->id)->get()->map(function($c){
            return 
        "<tr>
            <td>
            ".$c->candidate->id."
            </td>
            <td>
                 ".$c->candidate->member_id."
            </td>
            <td>
                <a href='/admin/get-candidate/".$c->candidate->id."'>".$c->candidate->name."</a>
            </td>
            <td>
                $c->phase_1
            </td>
            <td>
                $c->phase_2
            </td>
            <td>
                $c->phase_3
            </td>
            <td>
                $c->phase_4
            </td>
            <td>
                $c->phase_5
            </td>
            <td>
                $c->phase_6
            </td>
            <td>
                $c->phase_7
            </td>
            <td>
                $c->phase_8
            </td>
            <td>
                $c->phase_9
            </td>
            <td>
                $c->phase_10
            </td>
            <td>
                $c->phase_11
            </td>
            <td>
                $c->phase_12
            </td>
            <td>
                $c->phase_13
            </td>
            <td>
                $c->phase_14
            </td>
            <td>
                $c->phase_15
            </td>
            <td>
                $c->phase_16
            </td>
            <td>
                $c->phase_17
            </td>
            <td>
                $c->phase_18
            </td>
            <td>
                $c->phase_19
            </td>
            <td>
                $c->phase_20
            </td>
            <td>
                $c->phase_21
            </td>
            <td>
                $c->popularization_no
            </td>
            <td>
                $c->state
            </td>
        </tr>";

        })->toArray();
        if($districtBodyCandidates){
            
            $candidatesHtml .= "
            <b style='margin-top:50px'>District Body Members</b>
            <table class='table table-striped' >
            <thead>
                <tr>
                    <th>id</th>
                    <th>Member ID</th>
                    <th>Name</th>
                    <th >إقتراح هيئة القضاء</th>
                    <th >استلام أمانة السر</th>
                    <th >VP إلى SG إرسال </th>
                    <th>موافقة اللجان SGطلب ال</th>
                    <th >العلاقات العامة</th>
                    <th >امانة السر</th>
                    <th >لجنة المال</th>
                    <th>الموارد البشرية</th>
                    <th>ماكينة انتخابية</th>
                    <th>معلوماتية</th>
                    <th>انتشار</th>
                    <th>نشاطات ولوجستي</th>
                    <th>شؤون مرأة</th>
                    <th>شباب ورياضة</th>
                    <th>بلديات</th>
                    <th >ادارة ومراجعات</th>
                    <th>اعلام</th>
                    <th>VP ارسال الى</th>
                    <th>VP إلى SG إرسال نهائي من</th>
                    <th >VP موافقة</th>
                    <th >وتعميم GB من SG إستلام</th>
                    <th >رقم التعميم</th>
                    <th>State</th>
                </tr>
            </thead>
            <tbody>
                ".join(" ",$districtBodyCandidates)."
            </tbody>

            </table>
            <br>
            ";
        }

        $localBodyCandidates = LocalBody::where("registerer_id",$candidate->id)->get()->map(
            function($c){
                return "
                <tr>
                    <td>
                    ".$c->candidate->id."
                    </td>
                    <td>
                        ".$c->candidate->member_id."
                    </td>
                    <td>
                        <a href='/admin/get-candidate/".$c->candidate->id."'>".$c->candidate->name."</a>
                    </td>


                    <td>
                        $c->district
                    </td>


                    <td>
                        $c->region
                    </td>

                    <td>
                        $c->phase_1
                    </td>
                    <td>
                        $c->phase_2
                    </td>
                    <td>
                        $c->phase_3
                    </td>
                    <td>
                        $c->phase_4
                    </td>
                    <td>
                        $c->phase_5
                    </td>
                    <td>
                        $c->phase_6
                    </td>
                    <td>
                        $c->phase_7
                    </td>
                    <td>
                        $c->phase_8
                    </td>

                    <td>
                        $c->popularization_no
                    </td>
                    <td>
                        $c->state
                    </td>
                </tr>
                ";
            }
        )->toArray();
        
        if($localBodyCandidates){
            $candidatesHtml .="
            <b style='margin-top:100px'>Local Body Members</b>
            <table class='table table-striped'>
            <thead>
                <tr>
                    <th>id</th>
                    <th>Memeber Id</th>
                    <th>Name</th>

                    <th>قضاء</th>
                    <th>بلدة</th>

                    <th>إقتراح الهيئة</th>
                    <th>موافقة القضاء</th>
                    <th>موافقة القطاع</th>
                    <th>إستلام أمانة السر</th>
                    <th>VP إلى SG إرسال</th>
                    <th>VP موافقة</th>
                    <th>VP إلى SG إرسال نهائي</th>
                    <th>وتعميم GB من SG إستلام</th>
                    
                    <th>رقم التعميم</th>
                    <th>State</th>
                </tr>
            </thead>
            <tbody>
                ".join(" ",$localBodyCandidates)."
            </tbody>
            </table>
            <br>
            ";
        }
        
        $centralCommitteeCoordinatorCandidates = CentralCommitteeCoordinator::where("registerer_id",$candidate->id)->get()->map(
            function($c){
    
                return "
                <tr>
                    <td>
                    ".$c->candidate->id."
                    </td>

                    <td>
                        ".$c->candidate->member_id."
                    </td>
                    <td>
                        <a href='/admin/get-candidate/".$c->candidate->id."'>".$c->candidate->name."</a>
                    </td>
                    <td>
                        $c->phase_1
                    </td>
                    <td>
                        $c->phase_2
                    </td>
                    <td>
                        $c->phase_3
                    </td>
                    <td>
                        $c->phase_4
                    </td>
                    <td>
                        $c->phase_5
                    </td>
                    <td>
                        $c->phase_6
                    </td>
                    <td>
                        $c->phase_7
                    </td>
                    <td>
                        $c->popularization_no
                    </td>
                    <td>
                        $c->state
                    </td>
                </tr>
                ";
            }
        )->toArray();
        

        if($centralCommitteeCoordinatorCandidates){
            $candidatesHtml .="
            <b style='margin-top:100px'>Central Committee Coordinator</b>
            <table class='table table-striped'>
            <thead>
                <tr>
              <th>id</th>
              <th>Member ID</th>
              <th>Name</th>

              <th>VP لإقتراح SG إستلام </th>
              <th>SG طلب تحضير التعمييم من </th>
              <th>SG إستلام التعميم من قبل</th>
              <th>VP إلى SG إرسال التعميم من </th>
              <th>على إصدار التعميم VP موافقة ال </th>
              <th>VP إلى SG إرسال نهائي من</th>
              <th>وتعميم GB من SG إستلام</th>
                
              <th>رقم التعميم</th>
              <th>State</th>
                </tr>
            </thead>
            <tbody>
                ".join(" ",$centralCommitteeCoordinatorCandidates)."
            </tbody>
            </table>
            <br>
            ";
        }
        
        
        $centralCommitteeCandidates = CentralCommittee::where("registerer_id",$candidate->id)->get()->map(
            function($c){
                return "
                <tr>
                    <td>
                    ".$c->candidate->id."
                    </td>
                    <td>
                        ".$c->candidate->member_id."
                    </td>

                    <td>
                        <a href='/admin/get-candidate/".$c->candidate->id."'>".$c->candidate->name."</a>
                    </td>
                    <td>
                        ".$c->committee->name."
                    </td>
                    <td>
                        $c->phase_1
                    </td>
                    <td>
                        $c->phase_2
                    </td>
                    <td>
                        $c->phase_3
                    </td>
                    <td>
                        $c->phase_4
                    </td>
                    <td>
                        $c->phase_5
                    </td>
                    <td>
                        $c->phase_6
                    </td>
                    <td>
                        $c->popularization_no1
                    </td>
                    <td>
                        $c->phase_7
                    </td>
                    <td>
                        $c->phase_8
                    </td>
                    <td>
                        $c->phase_9
                    </td>

                    <td>
                        $c->popularization_no2
                    </td>
                    <td>
                        $c->state
                    </td>
                </tr>
                ";
            }
        )->toArray();
        

        if($centralCommitteeCandidates){
            $candidatesHtml .="
            <b style='margin-top:100px'>Central Committee Candidate</b>
            <table class='table table-striped'>
            <thead>
            <tr>
              <th>id</th>
              <th>Member ID</th>
              <th>Name</th>
              <th>Committee</th>

              <th>VP لإقتراح SG إستلام </th>
              <th>SG طلب تحضير التعمييم من </th>
              <th>SG إستلام التعميم من قبل</th>
              <th>VP إلى SG إرسال التعميم من </th>
              <th>على إصدار التعميم VP موافقة ال </th>
              <th>وتعميم GB من SG إستلام</th>

              <th>GB رقم التعميم</th>
              
              <th>VP موافقة</th>
              <th>على إصدار التعميم VP موافقة</th>
              <th>وتعميم GB من SG إستلام</th>
              
              <th>GB رقم التعميم </th>

              <th>State</th>
                </tr>
            </thead>
            <tbody>
                ".join(" ",$centralCommitteeCandidates)."
            </tbody>
            </table>
            <br>
            ";
        }

        $style = [
            "th {white-space:nowrap;}",
            "table td{ border-left:1px solid #e4e4e4;}",
            "table td:first-child{ border-left:0px;}"
            ];
//----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        
       return view('components.form')->with([
        'style' => $style,
        'layout'         => 'layouts.cms',
        'pageTitle'		=> $candidate->name,
        'method'		=> 'post',
        'form_action'	=> route('admin.faqs.store'),

        'boxes' => [
            [
                'wrapper-class' => 'col-md-12',
                'class' => 'box-primary',
                'box-header' => "Personal Details",
                'form_fields' => [
                    "
                    <div class='row'>
                        <div class='col-md-2'>
                        Phone Number:
                        </div>
                        <div class='col-md-10'>
                            <p>$candidate->phone_number</p>
                        </div>
                    </div>

                    <div class='row'>
                        <div class='col-md-2'>
                            Member ID:
                        </div>
                        <div class='col-md-10'>
                            <p>$candidate->member_id</p>
                        </div>
                    </div>
                    
                    
                    <div class='row'>
                    <div class='col-md-2'>
                        Email:
                    </div>
                    <div class='col-md-10'>
                        <p>$candidate->email</p>
                    </div>
                    </div>
                    "
                ]
            ],
            [
                'wrapper-class' => 'col-md-12',
                'class' => 'box-primary',
                'box-header' => "Positions",
                'form_fields' => [
                    $html
                ]
            ],
         
            [
                'wrapper-class' => 'col-md-12',
                'class' => 'box-primary',
                'box-header' => "Candidates",
                'form_fields' => [
                    "<div style='width:100%;overflow-x:scroll'>
                             $candidatesHtml
                    </div>"
                ],
            ],

        ]
    ]);
    }
}
