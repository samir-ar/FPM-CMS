<?php
namespace App\Http\Traits;

use App\AppUser;
use Hash;
use Auth;

trait TokenTrait
{

    public function generate_token()
    {
        return hash('sha512', sprintf('%04X%04X%04X%04X', mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535)));
    }

    public function generatePIN($digits = 4)
    {
        $i = 0; //counter
        $pin = ""; //our default pin is blank.
        while($i < $digits){
            //generate a random number between 0 and 9.
            $pin .= mt_rand(0, 9);
            $i++;
        }

        return $pin;
    }


    public function attempt($array){
        $user = Auth::guard('appUser')->attempt($array);

        if(!$user)
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
        $user = AppUser::where('token', $token)->first();

        return $user;
    }

}
?>
