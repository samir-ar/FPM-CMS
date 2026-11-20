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
use App\Http\Traits\TracksUserActivityTrait;
use App\Http\Controllers\Controller;
use App\Http\Repositories\UserRepository;
use App\Http\Repositories\V2\RegistrationRepository;
use App\V2\AppUser;
use App\V2\Setting;
use App\Services\QrCodeGenerator;

class RegistrationController extends Controller
{
    use FileTrait;
    use ResponseTrait;
    use TracksUserActivityTrait;


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

        // Enforced here (not just the once-per-launch splash check) because
        // the app already sends build_number on this exact request - no new
        // app release needed for this to take effect. Rejecting before the
        // OTP is even sent also avoids wasting an SMS on a version we're
        // about to turn away anyway. Respects the same per-platform Force
        // Update checkboxes/version numbers as the general update-check
        // feature - checked per the submitted `platform` when present
        // (Android and iOS can then be force-updated independently, as the
        // two separate checkboxes imply). Falls back to the old
        // platform-agnostic max() of both versions for an already-installed
        // app that predates the `platform` field, so it still gets caught
        // by whichever flag is on rather than silently bypassing the gate.
        $settings = Setting::first();
        $platform = $request->input('platform');
        if ($platform === 'ios') {
            $forceUpdateOn = (bool) $settings->force_update_ios;
            $minBuild = (int) $settings->ios_version;
        } elseif ($platform === 'android') {
            $forceUpdateOn = (bool) $settings->force_update_android;
            $minBuild = (int) $settings->android_version;
        } else {
            $forceUpdateOn = $settings->force_update_android || $settings->force_update_ios;
            $minBuild = max((int) $settings->android_version, (int) $settings->ios_version);
        }
        if ($forceUpdateOn) {
            $submittedBuild = (int) $request->input('build_number', 0);
            if ($submittedBuild < $minBuild) {
                return $this->api_error_response('app_update_required', 101, $settings->update_message ?? 'يرجى تحديث التطبيق للمتابعة');
            }
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

        if(!$fpmResponse->member_status){
            return $this->api_error_response('invalid_parameters', 101, 'الحساب مغلق. يرجى التواصل مع أمانة سر التيار الوطني الحر.');
        }

        // Too many code requests for one member in a short time (SMS cost, and a stranger repeatedly asking for codes).
        $sendKey = \App\Support\LoginLimits::sendKey($request->input('member_id'));
        \App\Support\LoginLimits::record($request->input('member_id'), 'code', $request->ip());
        // 3 code requests per member per day. Apple's App Review number is exempt (it must always be able to sign in).
        $isAppleReview = ltrim(trim((string) $request->input('phone_number')), '+') === \App\Http\Repositories\V2\RegistrationRepository::APPLE_REVIEW_PHONE;
        if (!$isAppleReview) {
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($sendKey, \App\Support\LoginLimits::SEND_MAX)) {
                return $this->api_error_response('too_many_requests', 101, 'لقد تجاوزت الحد اليومي لطلب رمز التحقق، يرجى المحاولة غداً أو التواصل مع الدعم', 429);
            }
            \Illuminate\Support\Facades\RateLimiter::hit($sendKey, \App\Support\LoginLimits::SEND_WINDOW);
        }

        // $token is only the PENDING login token: it replaces the member's real one after the code is entered.
        $request->request->add(['image' => $fpmResponse->PersonImage, 'name' => $fpmResponse->UserFullName, 'token' => $token, 'email'=> $fpmResponse->Email]);



        //add user
        if(!$user = $userRepo->getUserByPhoneNumber(request('phone_number'), request('member_id'))){

            // a brand-new account gets an unusable placeholder token (and stays unverified) until the code is entered
            try {
                $user = $userRepo->addUser(array_merge($request->all(), ['token' => strtoupper(bin2hex(random_bytes(16)))]));
            } catch (\Illuminate\Database\QueryException $e) {
                // two logins of the same member at the same instant: the database allows only one active account per
                // member, so use the one that won the race
                if ($e->getCode() !== '23000' || !($user = $userRepo->getUserByPhoneNumber(request('phone_number'), request('member_id')))) {
                    throw $e;
                }
            }
        }

        else{
            //Always update user info to what retrieved from FPM


            $data = $request->all();

            try {
                $qr_code = QrCodeGenerator::generateBase64($data['member_id']);
            } catch (\Exception $e) {
                $qr_code = $user->qr_code; // keep existing if generation fails
            }
            $user->member_id = $data['member_id'];
            // Found 2026-10-21: this block already claims to "always update
            // user info to what's retrieved from FPM", but never actually
            // touched phone_number - so once a member's real number changed
            // in fpm_users, app_users.phone_number stayed frozen at
            // whatever it was on first login forever after, even though the
            // login itself correctly re-validates against fpm_users.MobileNumber
            // every time. Since the OTP SMS is sent to app_users.phone_number
            // (see RegistrationRepository::sendVerificationPin -> sendSMS),
            // this silently sent every OTP to the member's old, dead number.
            // ltrim(...,'+') matches UserRepository::normalizePhone()'s own
            // storage convention (used by addUser() for a brand new row).
            $user->phone_number = ltrim($data['phone_number'], '+');
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


        // the code goes out by SMS only: it is NOT part of the reply (anyone who knew a member id + phone number
        // could otherwise read it here and log in without the phone)
        $user->pending_token = $token;
        $user->save();
        $regRepo->sendVerificationPin($user);

        $data = [
            'token' => $token,
        ];


        return response()->json($data);
    }


    public function verify_pin(Request $request, RegistrationRepository $verificationRepo, UserRepository $userRepo)
    {
        //for now send phone_number
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'pin' => 'required',
        ]);

        if ($validator->fails())
        {
            return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
        }

        $result = $verificationRepo->completeVerification($request->all(), $request->ip());
        if (isset($result['error']))
        {
            return $this->api_error_response('invalid_parameters', 101, $result['error'], $result['status']);
        }
        $user = $result['user'];

        $this->recordUserActivity($user['id']);

        // The row's `image` is overwritten with the party's photo on every login;
        // when the member chose their own photo, present that one instead (the
        // model is returned as-is and not saved again, so the stored columns stay).
        if ($user instanceof \App\V2\AppUser && !empty($user->custom_image)) {
            $user->setAttribute('image', $user->custom_image);
        }

        return $user;
    }

}
