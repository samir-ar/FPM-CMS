<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\V2\Region;

class DistrictController extends Controller
{
    public function getRegionsByDistrictId($id){
         return Region::where("district_id",$id)->select("name",'id')->get();
    }
}
