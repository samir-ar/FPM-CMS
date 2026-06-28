<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;


class AppUser extends Authenticatable
{
    use SoftDeletes;

    protected $guard = 'appUser';

    protected $table = 'app_users';

    protected $guarded = ['id'];

    protected $fillable = ['verification_nb'];

    public function volunteer()
    {
        return $this->hasMany(Volunteer::class, 'user_id', 'id');
    }

    public function polls()
    {
        return $this->belongsToMany(Poll::class, 'users_polls', 'app_user_id', 'poll_id')
            ->withTimestamps()->withPivot('option_id');
    }

    public function notifications()
    {
        return $this->belongsToMany(Notification::class, 'users_notifications', 'user_id', 'notification_id')
            ->withPivot('viewed')->withTimestamps();
    }

    public function volunteers()
    {
        return $this->belongsToMany(Volunteer::class, 'users_volunteers', 'user_id', 'volunteer_id')
            ->withTimestamps();
    }

    public function news()
    {
        return $this->belongsToMany(News::class, 'users_news', 'user_id', 'news_id')->withTimestamps();
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'user_id', 'id');
    }

    public function talks()
    {
        return $this->hasMany(TalkToUs::class, 'user_id', 'id');
    }

    public function getUser()
    {
        return collect([
            'id' => $this->id,
            'name' => $this->name,
            'member_id' => $this->member_id,
            'phone_number' => $this->phone_number,
            'rate' => $this->rate,
            'token' => $this->token,
            'verification_nb' => $this->verification_nb,
            'verified' => $this->verified,
            'player_id' => $this->player_id,
            'image' => $this->image,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'email' => $this->email,
            'date_of_birth' => $this->date_of_birth,
            'qr_code_image' => $this->qr_code,
        ]);
    }
    public static $IMAGE_PATH="images/qrcode";



    public function districtCoordinatorCandidates()
    {
        return $this->hasMany(DistrictCoordinator::class,'candidate_id');
    }



    public function districtBodiesCandidates()
    {
        return $this->hasMany(DistrictBody::class,"candidate_id");
    }


    public function districtBodiesRegisterers()
    {
        return $this->hasMany(DistrictBody::class,"registerer_id");
    }


    public function localBodiesRegisterers()
    {
        return $this->hasMany(LocalBody::class,"registerer_id");
    }


    public function localBodiesCandidates()
    {
        return $this->hasMany(LocalBody::class,"candidate_id");
    }


    public function centralCommitteesCandidates()
    {
        return $this->hasMany(CentralCommittee::class,"candidate_id");
    }


    public function centralCommitteesRegisterers()
    {
        return $this->hasMany(CentralCommittee::class,"registerer_id");
    }


    public function centralCommitteeCoordinator()
    {
        return $this->hasMany(CentralCommitteeCoordinator::class,"candidate_id");
    }

    public function consultingCommitteeCandidates(){
        return $this->hasMany(ConsultingCommittee::class,"candidate_id");
    }

    public function nationalCouncilPollPermitted(){
        //return $this->hasMany(CouncilNationalPollPermission::class, 'user_id');

        ### Jihad Update #####
        return CouncilNationalPollPermission::where('member_id',$this->member_id);
        ######################
    }

    public function getNameWithMemberIdAttribute(){
        return $this->name.' '.$this->member_id;
    }

    public function votes(){
        return $this->belongsToMany(InternalElectionCandidate::class,"internal_election_votes","user_id","candidate_id")->withPivot('rank')->withPivot('internal_election_id')->withPivot('weight');
    }

    //National Council votes
    public function nationalCouncilVotes(){
        return $this->belongsToMany(CouncilNationalPoll::class,'council_national_poll_votes','poll_id','user_id');
    }





}
