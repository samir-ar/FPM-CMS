<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Repositories\FpmApisRepository;
use App\Http\Repositories\RegistrationRepo;
use Illuminate\Http\Request;


use Hash;
use JWTAuth;
use Exception;
use Validator;
use App\Http\Requests;
use App\Http\Traits\FileTrait;
use App\Http\Traits\TokenTrait;
use App\Http\Traits\ResponseTrait;
use App\Http\Controllers\Controller;
use App\Http\Repositories\UserRepository;
use App\Http\Repositories\V2\RegistrationRepository;
use App\V2\AppUser;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class RegistrationController extends Controller
{
    use FileTrait;
    use ResponseTrait;


    public function sendVerfication(Request $request, FpmApisRepository $fpmRepo, UserRepository $userRepo, RegistrationRepository $regRepo)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required',
            'phone_number' => 'required',
            // 'build_number' => 'required',
            'player_id' => '',
        ]);

        if ($validator->fails())
        {
            return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
        }

        //check if the user credentials are
        //correct, if yes register the user
        //and send the verification
        try{
            $fpmResponse = $fpmRepo->getToken($request->all());

        }catch(Exception $exception){
            return $this->api_error_response('invalid_parameters', 101, 'Member Id invalid');
        }

        $randomBytes = random_bytes(16);
        $token = strtoupper(bin2hex($randomBytes));

        if(!isset($fpmResponse) && !$fpmResponse){
            return $this->api_error_response('invalid_parameters', 101, 'Invalid Credentials');
        }

        $request->request->add(['image' => $fpmResponse->PersonImage, 'name' => $fpmResponse->UserFullName, 'token' => $token, 'email'=> $fpmResponse->Email]);



        //add user
        if(!$user = $userRepo->getUserByPhoneNumber(request('phone_number'))){

            $user = $userRepo->addUser($request->all());
        }

        else{
            //Always update user info to what retrieved from FPM


            $data = $request->all();

            try {
                $qr_code = base64_encode(QrCode::format('png')->size(300)->errorCorrection('H')->generate($data['member_id']));
            } catch (\Exception $e) {
                $qr_code = $user->qr_code; // keep existing if imagick not available
            }
            $user->member_id = $data['member_id'];
            $user->token = $data['token'];
            $user->image = base64_encode($data['image']);
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->qr_code = $qr_code;

            if(isset($data['player_id'])){
                $existing_users = AppUser::where('player_id', $data['player_id'])->get();
                foreach($existing_users as $u){
                    $u->player_id = null;
                    $u->save();
                }
                $user->player_id = $data['player_id'];
            }

            $user->save();
        }


        $pin = $regRepo->sendVerificationPin($user);



        //$pin = '9999';

        //send verification
        $data = [
            'token' => $user->token,
            'pin'   => $pin,
        ];


        return response()->json($data);
    }


    public function verify_pin(Request $request, RegistrationRepository $verificationRepo, UserRepository $userRepo)
    {
        //for now send phone_number
        $validator = Validator::make($request->all(), [
            'token' => 'required|exists:app_users,token',
            'pin' => 'required|exists:app_users,verification_nb',
        ]);

        if ($validator->fails())
        {
            return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
        }

        if(!$user=$verificationRepo->checkVerificationCode($request->all()))
        {
            return $this->api_error_response('invalid_parameters', 101, 'Invalid Pin Code');
        }

        return $user;
    }

}
