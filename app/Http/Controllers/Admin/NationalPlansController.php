<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\V2\NationalPlan;
use App\Http\Traits\FileTrait;
use App\Http\Traits\FormTrait;
use App\Http\Controllers\Controller;
use DataTables;

class NationalPlansController extends Controller
{
    use FileTrait, FormTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = NationalPlan::orderBy('tab')->orderBy('order');
            return DataTables::of($data)
                ->addColumn('tab_label', fn($r) => NationalPlan::$tabs[$r->tab] ?? $r->tab)
                ->addColumn('action', fn($r) =>
                    "<a href='" . route('admin.national-plans.edit', $r->id) . "' class='btn btn-xs btn-info' style='margin-right:4px'><i class='fa fa-edit'></i></a>" .
                    "<a data-toggle='modal' class='delete-link btn btn-xs btn-danger' href='#deleteModal' id='" .
                    route('admin.national-plans.destroy', $r->id) . "'><i class='fa fa-trash'></i></a>")
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'الخطط الوطنية المقدمة',
            'table_title' => '',
            'slug'        => 'national-plans',
            'custom_btn'  => "<a href='" . route('admin.national-plans.create') . "' class='btn btn-primary'>إضافة خطة</a>",
            'headers'     => ['id', 'القطاع', 'العنوان', 'Action'],
            'action'      => route('admin.national-plans.index'),
            'columns'     => json_encode([
                ['data' => 'id',        'name' => 'id'],
                ['data' => 'tab_label', 'name' => 'tab_label'],
                ['data' => 'title',     'name' => 'title'],
                ['data' => 'action',    'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    public function create()
    {
        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'إضافة خطة — الخطط الوطنية المقدمة',
            'method'      => 'post',
            'form_action' => route('admin.national-plans.store'),
            'boxes' => [[
                'wrapper-class' => 'col-md-12',
                'class'         => 'box-default',
                'box-header'    => 'Info',
                'form_fields'   => [
                    $this->drawHtml('select-box', 'القطاع', 'tab', null, NationalPlan::$tabs, '', 'col-md-12 required'),
                    $this->drawHtml('small_text', 'العنوان', 'title', null, null, '', 'col-md-12 required'),
                    $this->drawHtml('file', 'ملف PDF', 'pdf', null, 'application/pdf', '', 'col-md-12 required'),
                    $this->drawHtml('small_text', 'الترتيب', 'order', '0', null, '', 'col-md-6'),
                ],
            ]],
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'tab'   => 'required|in:iqtisad,kahraba,maa,muhajareen,lamarkaziya',
            'title' => 'required',
            'pdf'   => 'required|mimes:pdf|max:20480',
            'order' => 'nullable|integer',
        ]);

        $plan = new NationalPlan();
        $plan->tab       = $request->tab;
        $plan->title     = $request->title;
        $plan->file_name = $this->moveFile($request->file('pdf'), 'national_plans');
        $plan->order     = $request->order ?? 0;
        $plan->save();

        return redirect()->route('admin.national-plans.index')
            ->with('message', 'تمت إضافة الخطة بنجاح');
    }

    public function edit($id)
    {
        $plan = NationalPlan::findOrFail($id);
        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'تعديل خطة — الخطط الوطنية المقدمة',
            'method'      => 'update',
            'form_action' => route('admin.national-plans.update', $id),
            'boxes' => [[
                'wrapper-class' => 'col-md-12',
                'class'         => 'box-default',
                'box-header'    => 'Info',
                'form_fields'   => [
                    $this->drawHtml('select-box', 'القطاع', 'tab', $plan->tab, NationalPlan::$tabs, '', 'col-md-12 required'),
                    $this->drawHtml('small_text', 'العنوان', 'title', $plan->title, null, '', 'col-md-12 required'),
                    $this->drawHtml('file', 'ملف PDF جديد', 'pdf', null, 'application/pdf', '', 'col-md-12'),
                    $this->drawHtml('small_text', 'الترتيب', 'order', $plan->order, null, '', 'col-md-6'),
                ],
            ]],
        ]);
    }

    public function update(Request $request, $id)
    {
        $plan = NationalPlan::findOrFail($id);
        $this->validate($request, [
            'tab'   => 'required|in:iqtisad,kahraba,maa,muhajareen,lamarkaziya',
            'title' => 'required',
            'pdf'   => 'nullable|mimes:pdf|max:20480',
            'order' => 'nullable|integer',
        ]);
        $plan->tab   = $request->tab;
        $plan->title = $request->title;
        $plan->order = $request->order ?? 0;
        if ($request->hasFile('pdf')) {
            $this->removeFile('national_plans/' . $plan->file_name);
            $plan->file_name = $this->moveFile($request->file('pdf'), 'national_plans');
        }
        $plan->save();
        return redirect()->route('admin.national-plans.index')->with('message', 'تم التعديل بنجاح');
    }

    public function destroy($id)
    {
        $plan = NationalPlan::findOrFail($id);
        $this->removeFile('national_plans/' . $plan->file_name);
        $plan->delete();
        return back()->with('message', 'تم الحذف بنجاح');
    }
}
