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

        $imageUrl = $news->source_image
            ? \Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/storage/images/news/' . $news->source_image)
            : null;

        $snippet = \Illuminate\Support\Str::limit(strip_tags($news->details ?? ''), 200);

        return view('news-public', compact('news', 'imageUrl', 'snippet'));
    }
}
