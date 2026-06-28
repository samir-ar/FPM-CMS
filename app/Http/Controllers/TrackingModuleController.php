<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\V2\DistrictBody;
use App\V2\DistrictCoordinator;
use App\V2\CentralCommittee;
use App\V2\CentralCommitteeCoordinator;
use App\V2\LocalBody;
use App\V2\AppUser;
use App\V2\ConsultingCommittee;

class TrackingModuleController extends Controller
{
    use FormTrait;
    use FileTrait;   

    public function getCondidates(Request $request){
        $user = request("user");

        $districtBodyCoordinators = $this->getDistrictBodiesCoordinators($user);
        $districtBodyMembers = $this->getDistrictBodiesMemebers($user);
        
        $localBodiesMembers = $this->getLocalBodiesMembers($user);
        
        $centralCommitteesCoordinators = $this->getCentralCommitteesCoordinators($user);
        $centralCommitteesMemebers = $this->getCentralCommitteesMembers($user);

        $consultingCommitteeMemebers = $this->getConsultingCommittee($user);


        return view('tracker.tracker')
        ->with(
            [   'user'=>$user,

                //District coordinator
                'myDistrictBodyCoordinatorAppliction'=>$districtBodyCoordinators->filter(function ($value,$key) use ($user){
                    return $user->id === $value->candidate_id;
                }),

                'othersDistrictBodyCoordinatorsApplications'=>$districtBodyCoordinators->filter(function ($value,$key) use ($user){
                    return $user->id === $value->registerer_id;
                }),


                //District Body member
                'myDistrictBodyMembersApplication'=>$districtBodyMembers->filter(function ($value,$key) use ($user){
                    return $user->id === $value->candidate_id;
                }),
                
                'othersDistrictBodyMembersApplications'=>$districtBodyMembers->filter(function ($value,$key) use ($user){
                    return $user->id === $value->registerer_id;
                }),
                

                //Local Body Members
                'myLocalBodiesMembersApplication'=>$localBodiesMembers->filter(function ($value,$key) use ($user){
                    return $user->id === $value->candidate_id;
                }),

                'othersLocalBodiesMembersApplication'=>$localBodiesMembers->filter(function ($value,$key) use ($user){
                    return $user->id === $value->registerer_id;
                }),


                //Central Committee Coordinator
                'myCentralCommitteesCoordinatorsApplication'=>$centralCommitteesCoordinators->filter(function ($value,$key) use ($user){
                    return $user->id === $value->candidate_id;
                }),

                'othersCentralCommitteesCoordinatorsApplication'=>$centralCommitteesCoordinators->filter(function ($value,$key) use ($user){
                    return $user->id === $value->registerer_id;
                }),


                //Central Committee Members
                'myCentralCommitteesMemebersApplication'=>$centralCommitteesMemebers->filter(function ($value,$key) use ($user){
                return $user->id === $value->candidate_id;
                }),

                'othersCentralCommitteesMemebersApplication'=>$centralCommitteesMemebers->filter(function ($value,$key) use ($user){
                    return $user->id === $value->registerer_id;
                }),

                
                //Consulting  Committee Members
                'myConsultingCommitteeMembersApplication'=>$consultingCommitteeMemebers->filter(function ($value,$key) use ($user){
                    return $user->id === $value->candidate_id;
                }),
                
                #Till Now the user cannot registerer any consulting memeber

            ]);
    }

    public function getDistrictBodiesMemebers($user){
        $candidates = DistrictBody::where("registerer_id",$user->id)->orWhere('candidate_id',$user->id)->get();
        return $candidates;
    }

    public function getDistrictBodiesCoordinators($user){
        return DistrictCoordinator::where('candidate_id',$user->id)->get();
    }
    
    public function getLocalBodiesMembers($user){
        return LocalBody::where("registerer_id",$user->id)->orWhere('candidate_id',$user->id)->get();
    }

    public function getCentralCommitteesCoordinators($user){
        return CentralCommitteeCoordinator::where("registerer_id",$user->id)->orWhere('candidate_id',$user->id)->get();
    }

    public function getCentralCommitteesMembers($user){
        return CentralCommittee::where("registerer_id",$user->id)->orWhere('candidate_id',$user->id)->get();
    }
    
    public function getConsultingCommittee($user){
        return ConsultingCommittee::where('candidate_id',$user->id)->get();
    }
}
