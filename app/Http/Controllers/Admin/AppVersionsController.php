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
    use FileTrait;


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
                        $this->drawHtml('small_text', 'Internal Election Minimum Build Number (leave blank for no restriction — same number applies to both Android and iOS, since they share one build-number sequence)', 'internal_election_min_build', $settings->internal_election_min_build, null, '', 'col-md-12'),
                        $this->drawHtml('small_text', 'National Council Poll Minimum Build Number (leave blank for no restriction — set only AFTER the new app release is live in both stores, same number for Android and iOS)', 'national_council_min_build', $settings->national_council_min_build, null, '', 'col-md-12'),

                    ]
                    ],[

                       'wrapper-class' => 'col-md-12',
                       'class' => 'box-default',
                       'box-header' => 'Message',
                       'form_fields' =>[
                            $this->drawHtml('small_text', 'Update Message Title', 'update_title', $settings->update_title, null, '', 'col-md-12'),
                            $this->drawHtml('small_text', 'Update Message Text', 'update_message', $settings->update_message, null, '', 'col-md-12'),
                        ]
                    ],[
                       'wrapper-class' => 'col-md-12',
                       'class' => 'box-default',
                       'box-header' => 'Landing page (fpmmobile.com) - phone picture',
                       'form_fields' =>[
                            $this->drawHtml('link', 'Current picture', 'current_landing_phone_image', Setting::landingPhoneUrl(), 'View the picture shown now', 'col-md-12'),
                            $this->drawHtml('file', 'New phone picture (PNG / JPG / WEBP, max 5 MB). Leave empty to keep the current one.', 'landing_phone_image', null, 'image/png,image/jpeg,image/webp', '', 'col-md-12'),
                            $this->drawHtml('checkbox', 'Go back to the original built-in picture', 'reset_landing_phone_image', false, null, '', 'col-md-12'),
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
            'update_message' => 'required',
            'landing_phone_image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:5120',
        ]);

        $settings = Setting::first();

        $settings->android_version = request('android_version');
        $settings->ios_version = request('ios_version');
        $settings->update_title = request('update_title');
        $settings->update_message = request('update_message');
        $settings->force_update_android = request('force_update_android')?true:false;

        $settings->force_update_ios = request('force_update_ios')?true:false;

        $settings->internal_election_min_build = request('internal_election_min_build') !== null && request('internal_election_min_build') !== ''
            ? (int) request('internal_election_min_build')
            : null;

        $settings->national_council_min_build = request('national_council_min_build') !== null && request('national_council_min_build') !== ''
            ? (int) request('national_council_min_build')
            : null;

        if ($request->hasFile('landing_phone_image')) {
            $settings->landing_phone_image = $this->moveFile($request->file('landing_phone_image'), 'landing');
        } elseif ($request->has('reset_landing_phone_image')) {
            $settings->landing_phone_image = null;
        }

        $settings->save();

        return redirect()->back()->with('message', "Updated successfully");
    }
}
