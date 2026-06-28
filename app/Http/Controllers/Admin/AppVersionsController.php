<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\V2\Setting;

class AppVersionsController extends Controller
{
    use FormTrait;


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id=1)
    {
        $settings = Setting::first();
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Edit ',
            'method'		=> 'update',
            'form_action'	=> route('admin.app-versions.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Versions',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Android Version', 'android_version', $settings->android_version, null, '', 'col-md-6'),
                        $this->drawHtml('small_text', 'IOS Version', 'ios_version', $settings->ios_version, null, '', 'col-md-6'),

                        $this->drawHtml('checkbox', 'Android Force Update', 'force_update_android', $settings->force_update_android, null, '', 'col-md-6'),
                        $this->drawHtml('checkbox', 'IOS Force Update', 'force_update_ios', $settings->force_update_ios, null, '', 'col-md-6'),

                    ]
                    ],[

                       'wrapper-class' => 'col-md-12',
                       'class' => 'box-default',
                       'box-header' => 'Message',
                       'form_fields' =>[
                            $this->drawHtml('small_text', 'Update Message Title', 'update_title', $settings->update_title, null, '', 'col-md-12'),
                            $this->drawHtml('small_text', 'Update Message Text', 'update_message', $settings->update_message, null, '', 'col-md-12'),
                        ]
                    ]


            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $this->validate($request, [
            'android_version' => 'required',
            'ios_version' => 'required',
            'update_title' => 'required',
            'update_message' => 'required'
        ]);

        $settings = Setting::first();

        $settings->android_version = request('android_version');
        $settings->ios_version = request('ios_version');
        $settings->update_title = request('update_title');
        $settings->update_message = request('update_message');
        $settings->force_update_android = request('force_update_android')?true:false;
        
        $settings->force_update_ios = request('force_update_ios')?true:false;

        
        $settings->save();

        return redirect()->back()->with('message', "Updated successfully");
    }
}
