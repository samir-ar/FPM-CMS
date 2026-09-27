<?php

namespace App\Http\Middleware;

use Exception;
use Closure;
use App\Http\Traits\V2\TokenTrait;
use App\Http\Traits\ResponseTrait;
use App\Http\Traits\TracksUserActivityTrait;

class MyAuthV2
{
    use TokenTrait;
    use ResponseTrait;
    use TracksUserActivityTrait;

    public function handle($request, Closure $next)
    {
        try {
            $error_code = 100;

            $token = $request->header('token');


            $user = $this->toUser($token);


            if(!$user){
                if (!$token)
                    $error_code = 101;

                throw new Exception('Invalid Access Token');
            }


            if(!$user->verified){
                $error_code = 101;

                throw new Exception('User Did not finish verification');

            }

            if(!$user->member_status){
                $error_code = 101;

                throw new Exception('الحساب مغلق. يرجى التواصل مع أمانة سر التيار الوطني الحر.');
            }



        } catch (Exception $e) {

            return $this->api_error_response('invalid_token', $error_code, $e->getMessage(), 401);
        }

        $request->merge(['user' => $user]);

        $this->recordUserActivity($user->id);

        return $next($request);
    }
}
