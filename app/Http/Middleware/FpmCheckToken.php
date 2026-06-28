<?php

namespace App\Http\Middleware;

use App\Http\Traits\ResponseTrait;
use App\Http\Repositories\FpmApisRepository;
use App\V2\AppUser;
use App\V2\FpmUser;
use Closure;
use Exception;

class FpmCheckToken
{

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

            // $build_number = $request->header('build-number');


            // $fpmRepo = new FpmApisRepository();
            // $data = [
            //     'build_number' => $build_number,
            //     'token' => $token,
            // ];

            // $fpmUser = $fpmRepo->verifyToken($data);
            $getUser = AppUser::where('token', $request->header('token'))->first();
            $fpmUser = FpmUser::where('MemberId', $getUser->member_id)->with('fpmUsersActions')->first();

            // dd($fpmUser->fpmUsersActions);

            $request->merge(['loggedInUser' => $fpmUser]);

            // if(!$fpmUser->AccessToken)
            //     throw new Exception('Administrator Removed User');

            $member_groups = $fpmUser->fpmUsersActions;

            $groups = collect($member_groups)->pluck('GroupId')->toArray();
            $groups_info = collect($member_groups);

        } catch (Exception $e) {

            return $this->api_error_response('invalid_token', 100, 'Mismatch Token');
        }

        $request->merge(['groups' => $groups, 'groups_info' => $groups_info]);

        return $next($request);
    }
}
