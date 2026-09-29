<?php

namespace App\Imports;

use App\V2\CouncilNationalPollPermission;
use App\V2\AppUser;
use App\Exceptions\InvalidVotersFileException;
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
            $memberId = trim((string) ($row[0] ?? ''));

            // Skips blank rows and the template's own header row (a real
            // FPM member ID is always numeric).
            if ($memberId === '' || !ctype_digit($memberId)) continue;

            // Both columns are mandatory — fail the whole import with a
            // clear message rather than silently guessing a weight or
            // crashing with a raw PHP error.
            $weight = trim((string) ($row[1] ?? ''));
            if ($weight === '' || !ctype_digit($weight)) {
                throw new InvalidVotersFileException(
                    'يجب إدخال رقم الانتساب (Member ID) والوزن (Weight) لكل عضو — كلا الحقلين مطلوبان. الرجاء تحميل النموذج المرفق وتعبئته بشكل صحيح.'
                );
            }

            CouncilNationalPollPermission::create([
                'member_id'=> $memberId,
                //'user_id' => $account->id,
                'poll_id' => $this->pollId,
                'vote_weight' => $weight
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
