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
        // The app sends a clean 2-letter value ("en"/"ar"), but a real
        // browser sends its full native header (e.g.
        // "en-US,en;q=0.9,ar-EG;q=0.8") - passing that raw string straight
        // to App::setLocale() crashes with a 500 (Symfony's locale
        // validator rejects it), which is exactly what hitting any API
        // route directly from a browser (e.g. an admin-panel <img> tag)
        // used to do. getPreferredLanguage() parses the quality-value
        // syntax properly and matches it against what this app actually
        // ships translations for.
        $language = $request->getPreferredLanguage(['en', 'ar']) ?: 'en';

        $request->merge(['language' => $language]);
        App::setLocale($language);

        return $next($request);
    }
}
