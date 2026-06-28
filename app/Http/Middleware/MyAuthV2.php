<?php

namespace App\Http\Middleware;

use Exception;
use Closure;
use App\Http\Traits\V2\TokenTrait;
use App\Http\Traits\ResponseTrait;

class MyAuthV2
{
    use TokenTrait;
    use ResponseTrait;

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



        } catch (Exception $e) {

            return $this->api_error_response('invalid_token', $error_code, $e->getMessage());
        }

        $request->merge(['user' => $user]);

        return $next($request);
    }
}
