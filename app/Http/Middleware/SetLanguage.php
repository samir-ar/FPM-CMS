<?php

namespace App\Http\Middleware;

use App;
use Closure;

class SetLanguage
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $language = $request->header('Accept-Language') ? : 'en';

        $request->merge(['language' => $language]);
        App::setLocale($language);

        return $next($request);
    }
}
