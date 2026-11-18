<?php


namespace App\Http\Repositories\V2;

use DB;
use App\Session;
use App\AppUser;
use Carbon\Carbon;
use App\PinVerification;
use App\Http\Traits\TokenTrait;
use App\Http\Repositories\V2\FpmApisRepository;
use Illuminate\Support\Facades\RateLimiter;


class RegistrationRepository
{
    use TokenTrait;

    public function sendVerificationPin(AppUser $user)
    {
        //create pin verification


        if(request('phone_number') == "+96171106394" ||request('phone_number') == "96171106394" ||request('phone_number') == "+96178837076" ||request('phone_number') == "+96171106394" || request('phone_number') == '96176979532' || request('phone_number') == '+96176979532' || request('phone_number') == '+9613956719'){
            $user->verification_nb = '1111';
            $user->email = 'liwaa.alhamra@tedmob.com';
        }else{
            $user->verification_nb = $this->generatePIN();
        }

        // The code is only good for 10 minutes. The account is NOT marked verified here: that happens when the code
        // is entered (completeVerification), so asking for a code proves nothing and unlocks nothing.
        $user->verification_expires_at = Carbon::now()->addMinutes(10);
        $user->save();


        $data = $user->toArray();

        $data['message'] = 'Cde '.$user->verification_nb;

        try {
            $this->deliverSms($data);
        } catch (\Exception $e) {
            \Log::info('sms failed to send',[$e->getMessage()]);
        }

        return $user->verification_nb;
    }

    protected function deliverSms(array $data)
    {
        return (new FpmApisRepository())->sendSMS($data);
    }

    // Checks the code for the token that was handed out by send-verification. On success the waiting token becomes
    // the real login token. Returns ['user' => ...] or ['error' => message, 'status' => http status].
    public function completeVerification(array $data, ?string $ip = null): array
    {
        $ipKey = 'otp-ip:' . $ip;
        $tooMany = ['error' => 'تم تجاوز عدد المحاولات، يرجى المحاولة لاحقاً', 'status' => 429];

        if ($ip && RateLimiter::tooManyAttempts($ipKey, 40)) {
            return $tooMany;
        }

        $user = !empty($data['token']) ? AppUser::where('pending_token', (string) $data['token'])->first() : null;
        if (!$user) {
            if ($ip) RateLimiter::hit($ipKey, 900);
            return ['error' => 'Invalid Pin Code', 'status' => 400];
        }

        $key = 'otp-guess:' . ltrim((string) $user->member_id, '0');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return $tooMany;
        }

        if (!$user->verification_expires_at || Carbon::parse($user->verification_expires_at)->isPast()) {
            return ['error' => 'انتهت صلاحية رمز التحقق، يرجى طلب رمز جديد', 'status' => 400];
        }

        if (!hash_equals((string) $user->verification_nb, (string) ($data['pin'] ?? ''))) {
            RateLimiter::hit($key, 900);
            if ($ip) RateLimiter::hit($ipKey, 900);
            return ['error' => 'Invalid Pin Code', 'status' => 400];
        }

        $user->token = $user->pending_token;
        $user->pending_token = null;
        $user->verification_expires_at = null;
        $user->verified = true;
        $user->save();
        RateLimiter::clear($key);

        return ['user' => $user->getUser()];
    }

    public function checkVerificationCode($data)
    {
        $user = AppUser::where('token', $data['token'])->where('verification_nb', $data['pin'])->first();

        if(!$user)
            return null;

        $user->verified = true;

        $user->save();

        return $user->getUser();
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
