<?php

namespace App\Http\Controllers;

use App\FaqCategory;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    public function faqs()
    {
        $categories = FaqCategory::orderBy('order','asc')->get();

        return view('faqs')->with(compact('categories'));
    }

    /**
     * Public (no-auth) single-article page — the web fallback for anyone who
     * opens a shared news link without the app installed, and also the
     * actual App Link / Universal Link target the app intercepts when it
     * is installed.
     */
    public function showNews($id)
    {
        $news = \App\News::find($id);

        if (!$news) {
            abort(404);
        }

        // Some articles have source_image accidentally set to the app's own
        // icon file (a leftover placeholder, not a real photo) — treat that
        // the same as no image at all, rather than showing our own logo as
        // if it were the article's content in a link preview.
        $hasRealImage = $news->source_image && !str_contains($news->source_image, 'play-store-icon');

        $imageUrl = $hasRealImage
            ? \Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/storage/images/news/' . $news->source_image)
            : null;

        $snippet = \Illuminate\Support\Str::limit(html_entity_decode(strip_tags($news->details ?? '')), 200);

        // only news ticked "Public" in the news form shows its content on the web; a members-only
        // item shows just the download page (ids are sequential, so nothing may be shown by default)
        $isPublic = (bool) $news->show_for_guest;

        return view('news-public', compact('news', 'imageUrl', 'snippet', 'isPublic'));
    }

    /**
     * Public (no-auth) single-event page - the web fallback for a shared event
     * link, and the App Link / Universal Link target the app intercepts when it
     * is installed. Only an event ticked "Public" in the event form
     * (`show_for_guest`, the same flag that lets guests see it in the app) shows its
     * content here; a members-only event shows just the "download the app" page, so a
     * shared link can never leak it to the web.
     */
    public function showEvent($id)
    {
        $event = \App\V2\Event::find($id);

        if (!$event) {
            abort(404);
        }

        // the same tick as "visible to guests" in the app (events.show_for_guest, set in the event form)
        $isPublic = (bool) $event->show_for_guest;

        // preferred language first, then the other one: many events only have an
        // English (or only an Arabic) version of the text
        $order = ($event->strict_lang && $event->strict_lang == 'en') ? ['en', 'ar'] : ['ar', 'en'];
        $pick = function ($field) use ($event, $order) {
            foreach ($order as $lang) {
                $value = trim((string) $event->getTranslation($field, $lang));
                if ($value !== '') {
                    return $value;
                }
            }
            return '';
        };
        $name = $pick('name');
        $details = $pick('details');

        $imageUrl = $event->image
            ? \Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/storage/images/events/' . $event->image)
            : null;

        $snippet = \Illuminate\Support\Str::limit(html_entity_decode(strip_tags((string) $details)), 200);

        $when = null;
        if ($event->from_date) {
            $when = \Carbon\Carbon::parse($event->from_date)->format('Y-m-d H:i');
        }

        return view('event-public', compact('event', 'isPublic', 'name', 'details', 'imageUrl', 'snippet', 'when'));
    }
}
