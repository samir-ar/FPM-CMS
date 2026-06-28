<?php


namespace App\Http\Repositories;


use Hash;
use App\AppUser;
use App\Session;
use App\Http\Traits\TokenTrait;


class UserRepository
{
    use TokenTrait;


    public function addUser($data)
    {
        $user = new AppUser();
        $user->member_id = $data['member_id'];
        $user->phone_number = $data['phone_number'];
        $user->token = $data['token'];
        $user->image = base64_encode($data['image']);
        $user->name = $data['name'];

        if (isset($data['player_id'])) {
            $existing_users = AppUser::where('player_id', $data['player_id'])->get();
            foreach($existing_users as $u){
                $u->player_id = null;
                $u->save();
            }
            $user->player_id = $data['player_id'];
        }

        $user->save();

        return $user;
    }

    public function hasRegistered($phone_number)
    {
        $user = AppUser::select('id', 'verified', 'phone_number')->where('phone_number', $phone_number)->where('verified', true)->first();

        return $user ? true : false;
    }

    public function getUserByPhoneNumber($phone_number)
    {
        $user = AppUser::select('id', 'phone_number')->where('phone_number', $phone_number)->first();

        return $user;
    }
}
