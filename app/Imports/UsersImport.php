<?php

namespace App\Imports;

use App\V2\AppUser;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class UsersImport implements ToCollection
{
    public function __construct()
    {
        ini_set('max_execution_time', 2700);
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row)
        {
            //Excel structure |Memeber id| Full Name | Phone number

            //if any field is null discard this row
            if($row[0]=="" || $row[1]=="" || $row[2]=="" || $row[0]=="NULL" || $row[1]=="NULL" || $row[2]=="NULL" || !$row[0] ||!$row[1] || !$row[2] ){
                continue;
            }

            //Check if exist
            if(AppUser::where('member_id',$row[0])->where('phone_number',$row[2])->count()>0){
                continue;
            }

            AppUser::create([
                'member_id'=>$row[0],
                'name'=>$row[1],
                'phone_number'=>$row[2]
            ]);

        }
    }


}
