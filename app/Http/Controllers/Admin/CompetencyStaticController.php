<?php

namespace App\Http\Controllers\Admin;

use App\Content;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Controllers\Controller;

class CompetencyStaticController extends Controller
{
    use FormTrait;

    public function index()
    {
        $terms = Content::where('category', 'competency-terms')->first();
        $faq = Content::where('category', 'competency-faq')->first();

        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'منصة الكفاءات - الشروط والأسئلة الشائعة',
            'method'      => 'post',
            'form_action' => route('admin.competency-static.update'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'الشروط والأحكام',
                    'form_fields' => [
                        $this->drawHtml('text', 'المحتوى', 'terms', optional($terms)->text, null, '', 'col-md-12'),
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'الأسئلة الشائعة',
                    'form_fields' => [
                        $this->drawHtml('text', 'المحتوى', 'faq', optional($faq)->text, null, '', 'col-md-12'),
                    ],
                ],
            ],
        ]);
    }

    public function update(Request $request)
    {
        foreach (['terms' => 'competency-terms', 'faq' => 'competency-faq'] as $field => $category) {
            $r = Content::where('category', $category)->first();

            if (!$r) {
                $r = new Content;
                $r->category = $category;
            }

            $r->text = $request->input($field);
            $r->save();
        }

        return back()->with('message', 'تم تحديث المحتوى بنجاح');
    }
}
