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
}
