<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Traits\FileTrait;

class ImageUploadController extends Controller
{
             
    public function imageUpload(Request $request,$path)
    {
        $this->validate($request, [
            'myfile' => 'required|max:700'
            ]);
            
            $image = $request->file('myfile');
            
            $name = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
            
            
            
     return response()->json(['errors' => 'No Item' ])->setStatusCode(400);
 }

 public function uploadNewsImage(Request $request){
     return this->imageUpload($request,"images/news");
 }

 public function uploadEventImage(Request $request){
    return this->imageUpload($request,"images/events");
    }
}
