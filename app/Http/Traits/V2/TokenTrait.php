<?php

namespace App\Http\Traits\V2;

use App\V2\AppUser;
use Hash;
use Auth;

trait TokenTrait
{

    public function generate_token()
    {
        // random_bytes (CSPRNG): the old mt_rand() version drew from a predictable generator.
        return hash('sha512', random_bytes(32));
    }

    public function generatePIN($digits = 4)
    {
        $i = 0; //counter
        $pin = ""; //our default pin is blank.
        while ($i < $digits) {
            //generate a random number between 0 and 9.
            $pin .= random_int(0, 9);
            $i++;
        }

        return $pin;
    }


    public function attempt($array)
    {
        $user = Auth::guard('appUser')->attempt($array);

        if (!$user)
            return;

        $user = AppUser::where('email', $array['email'])->first();

        return $user->token;
    }



    /*
     * return user from user
     * here to check the expiry date
     *
     */

    public function toUser($token)
    {
        // $user = AppUser::where('token', $token)->first();
        $user = AppUser::select('id', 'member_id', 'rate', 'token', 'verification_nb', 'verified', 'player_id', 'name', 'phone_number', 'can_scan_checkin', 'district', 'town', 'sect', 'sect_number', 'date_of_birth', 'gender', 'member_status')->where('token', $token)->first();

        return $user;
    }
}
