<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\V2\DonationImage;

class DonationBannerController extends Controller
{
    use FormTrait;
    use FileTrait;
    public function changeImageForm(){

        $image = DonationImage::first();
        
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Change Donation Image',
            'method'		=> 'post',
            'form_action'	=> route('admin.donation.image.update'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-primary',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('image', 'image', 'image', (($image !== null) ?$image->image:""), null, '', 'col-md-12 required'),
                    ]
                ]
            ]
        ]);
    }


    public function changeImage(Request $request){
        $image= DonationImage::first();

        if(!$image){
            $image = new DonationImage();
        }
     
        if($image->image){
            $this->removeFile($image->image);
        }

        $image->image = $this->moveFile(request("image"),"/images/donations");        
        $image->save();
        return back()->with(['message', "Image has been updated successfully!"]);
    }

}