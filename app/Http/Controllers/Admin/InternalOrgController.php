<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\V2\InternalOrgDocument;
use App\Http\Traits\FormTrait;
use App\Http\Controllers\Controller;

class InternalOrgController extends Controller
{
    use FormTrait;

    public function index()
    {
        $nizham = InternalOrgDocument::where('tab', 'nizham_dakhili')->first();
        $tawjihat = InternalOrgDocument::where('tab', 'tawjihat_tatbiqiya')->first();

        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'التنظيم الداخلي',
            'method'      => 'post',
            'form_action' => route('admin.internal-org.update'),
            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class'         => 'box-default',
                    'box-header'    => InternalOrgDocument::$tabs['nizham_dakhili'],
                    'form_fields'   => [
                        $this->drawHtml('text', 'المحتوى', 'nizham_dakhili',
                            optional($nizham)->content, null, '', 'col-md-12'),
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-12',
                    'class'         => 'box-default',
                    'box-header'    => InternalOrgDocument::$tabs['tawjihat_tatbiqiya'],
                    'form_fields'   => [
                        $this->drawHtml('text', 'المحتوى', 'tawjihat_tatbiqiya',
                            optional($tawjihat)->content, null, '', 'col-md-12'),
                    ],
                ],
            ],
        ]);
    }

    public function update(Request $request)
    {
        foreach (array_keys(InternalOrgDocument::$tabs) as $tab) {
            InternalOrgDocument::updateOrCreate(
                ['tab' => $tab],
                ['content' => $request->input($tab)]
            );
        }

        return back()->with('message', 'تم تحديث المحتوى بنجاح');
    }
}
