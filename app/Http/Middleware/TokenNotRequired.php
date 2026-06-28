<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use App\Http\Traits\TokenTrait;
use App\Http\Traits\ResponseTrait;

class TokenNotRequired
{
    use TokenTrait;
    use ResponseTrait;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(!$token = $request->header('token'))
            return $next($request);

        try {

            $token = $request->header('token');


            $user = $this->toUser($token);


            if(!$user->verified)
                throw new Exception('User Did not finish verification');


        } catch (Exception $e) {

            return $this->api_error_response('invalid_token', 100, $e->getMessage());
        }

        $request->merge(['user' => $user, 'user_id' => $user ? $user->id : null]);

        return $next($request);
    }
}
