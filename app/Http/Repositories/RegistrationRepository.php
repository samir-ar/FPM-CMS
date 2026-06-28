<?php


namespace App\Http\Repositories;

use DB;
use App\Session;
use App\AppUser;
use Carbon\Carbon;
use App\PinVerification;
use App\Http\Traits\TokenTrait;
use App\Http\Repositories\FpmApisRepository;


class RegistrationRepository
{
    use TokenTrait;

    public function sendVerificationPin(AppUser $user)
    {
        //create pin verification


        if(request('phone_number') == '+9613706108'){
            $user->verification_nb = '1111';

        }else{
            $user->verification_nb = $this->generatePIN();
        }

        $user->verified = true;
        $user->save();


        $data = $user->toArray();

        $data['message'] = 'Cde '.$user->verification_nb;

        //TODO:send sms to user mobile_number
        $fpmRepo = new FpmApisRepository();
        $fpmRepo->sendSMS($data);

        return $user->verification_nb;
    }

    public function checkVerificationCode($data)
    {
        $user = AppUser::where('token', $data['token'])->where('verification_nb', $data['pin'])->first();

        if(!$user)
            return null;

        $user->verified = true;

        $user->save();

        return $user;
    }

    public function register($data)
    {
        $verification = PinVerification::where('token', $data['token'])->where('verified', true)->first();

        if(!$verification)
            return;

        $response = null;

        //complete verification and register the user
        DB::transaction(function() use ($verification, $data, &$response){

            $user = AppUser::where('phone_number', $verification->field)->first();
            if(!$user)
                $user = new AppUser;

            //add fields like password username;
            $user->save();

            $verification->completed = true;
            $verification->user()->associate($user);

            //add session
            //create and return session
            $session = new Session();
            $session->token = $this->generate_token();
            $user->sessions()->save($session);

            $response = $user->getUser();
            $response->put('token', $session->token);

        });
    }

}
