<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function store_file($link, $request_file, $name=null)
    {
        $tmp_file = $request_file;
        if(!$name){
            $name = $tmp_file->getClientOriginalName();
        }

        $original_name = rand(100,1000).str_replace(' ', '-', strtolower($name));

        $file = $tmp_file->storeAs('public/'.$link, $original_name);

        return $original_name;
    }
}
