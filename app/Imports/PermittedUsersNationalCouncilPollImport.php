<?php

namespace App\Imports;

use App\V2\CouncilNationalPollPermission;
use App\V2\AppUser;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class PermittedUsersNationalCouncilPollImport implements ToCollection
{
    public $pollId;
    public function __construct($pollId)
    {
        ini_set('max_execution_time', 2700);
        ini_set('memory_limit', '-1');
        $this->pollId = $pollId;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row)
        {
          
            /*
            $accounts = null;

            if($user  = AppUser::select('id','member_id')->where('member_id',$row[0])->orderBy('id','desc')->get()){
                $accounts =  $user;
            }else{
                continue;
            }

            foreach ($accounts as $account){
                CouncilNationalPollPermission::create([
                    'user_id' => $account->id,
                    'poll_id' => $this->pollId,
                    'vote_weight' => $row[1]
                ]);
            }
            */

            ###### Jihad Updates ######
            if(!$row[0]) continue;
            CouncilNationalPollPermission::create([
                'member_id'=> $row[0],
                //'user_id' => $account->id,
                'poll_id' => $this->pollId,
                'vote_weight' => $row[1]
            ]);
            ###########################
        }
    }


    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    /*public function model(array $row)
    {
           $id = null;

           if($user  = AppUser::where('member_id',$row[0])->orderBy('id','desc')->first()){
                $id = $user->id;
           }else{
               return null;
           }
        return new CouncilNationalPollPermission([
            'user_id' => $id,
            'poll_id' => $this->pollId,
            'vote_weight' => $row[1]
        ]);
    }*/
}
