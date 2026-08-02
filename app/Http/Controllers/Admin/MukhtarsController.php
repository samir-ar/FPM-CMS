<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\V2\Mukhtar;
use App\Http\Traits\FormTrait;
use App\Http\Controllers\Controller;
use App\Exports\MukhtarsTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use DataTables;

class MukhtarsController extends Controller
{
    use FormTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Mukhtar::query()->orderBy('qada')->orderBy('village_name')->orderBy('sort_order');
            return DataTables::of($data)
                ->addColumn('mountasib_badge', fn($row) =>
                    $row->is_mountasib
                        ? "<span class='badge' style='background:#f39c12;color:#fff;'>منتسب</span>"
                        : '')
                ->addColumn('action', fn($row) =>
                    "<a href='" . route('admin.mukhtars.edit', $row->id) . "' class='btn btn-xs btn-info' style='margin-right:4px'><i class='fa fa-edit'></i></a>" .
                    "<a data-toggle='modal' class='delete-link btn btn-xs btn-danger' href='#deleteModal' id='" .
                    route('admin.mukhtars.destroy', $row->id) . "'><i class='fa fa-trash'></i></a>")
                ->rawColumns(['mountasib_badge', 'action'])
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'مخاتير',
            'table_title' => '',
            'slug'        => 'mukhtars',
            'custom_btn'  =>
                "<a href='" . route('admin.mukhtars.create') . "' class='btn btn-primary' style='margin-right:6px'>إضافة مختار</a>" .
                "<a href='" . route('admin.mukhtars.import-form') . "' class='btn btn-success'>استيراد Excel</a>",
            'headers'     => ['#', 'القضاء', 'البلدة', 'الحي', 'الاسم', 'المنصب', 'منتسب', 'Action'],
            'action'      => route('admin.mukhtars.index'),
            'columns'     => json_encode([
                ['data' => 'id',              'name' => 'id'],
                ['data' => 'qada',            'name' => 'qada'],
                ['data' => 'village_name',    'name' => 'village_name'],
                ['data' => 'neighborhood',    'name' => 'neighborhood'],
                ['data' => 'full_name',       'name' => 'full_name'],
                ['data' => 'position',        'name' => 'position'],
                ['data' => 'mountasib_badge', 'name' => 'mountasib_badge', 'searchable' => false, 'sortable' => false],
                ['data' => 'action',          'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    public function create()
    {
        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'إضافة مختار',
            'method'      => 'post',
            'form_action' => route('admin.mukhtars.store'),
            'boxes' => [[
                'wrapper-class' => 'col-md-12',
                'class'         => 'box-default',
                'box-header'    => 'معلومات المختار',
                'form_fields'   => [
                    $this->drawHtml('small_text', 'القضاء', 'qada', null, null, '', 'col-md-6 required'),
                    $this->drawHtml('small_text', 'اسم البلدة / القرية', 'village_name', null, null, '', 'col-md-6 required'),
                    $this->drawHtml('small_text', 'اسم الحي', 'neighborhood', null, null, '', 'col-md-6'),
                    $this->drawHtml('small_text', 'الاسم الكامل', 'full_name', null, null, '', 'col-md-6 required'),
                    $this->drawHtml('select-box', 'المنصب', 'position', null,
                        ['مختار' => 'مختار', 'عضو اختياري' => 'عضو اختياري', 'متوفي' => 'متوفي'], '', 'col-md-6 required'),
                    $this->drawHtml('small_text', 'رقم الهاتف', 'phone', null, null, '', 'col-md-6'),
                    $this->drawHtml('select-box', 'منتسب', 'is_mountasib', null,
                        ['0' => 'لا', '1' => 'نعم (أورانج)'], '', 'col-md-6'),
                    $this->drawHtml('small_text', 'الترتيب', 'sort_order', '0', null, '', 'col-md-6'),
                ],
            ]],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'qada'         => 'required|string|max:191',
            'village_name' => 'required|string|max:191',
            'full_name'    => 'required|string|max:191',
            'position'     => 'required|in:مختار,عضو اختياري,متوفي',
        ]);

        Mukhtar::create([
            'qada'         => $request->qada,
            'village_name' => $request->village_name,
            'neighborhood' => $request->neighborhood ?: null,
            'full_name'    => $request->full_name,
            'position'     => $request->position,
            'phone'        => $request->phone ?: null,
            'is_mountasib' => $request->boolean('is_mountasib'),
            'sort_order'   => (int) $request->sort_order,
        ]);

        return redirect()->route('admin.mukhtars.index')
            ->with('message', 'تمت الإضافة بنجاح');
    }

    public function edit($id)
    {
        $m = Mukhtar::findOrFail($id);

        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'تعديل مختار',
            'method'      => 'update',
            'form_action' => route('admin.mukhtars.update', $id),
            'boxes' => [[
                'wrapper-class' => 'col-md-12',
                'class'         => 'box-default',
                'box-header'    => 'معلومات المختار',
                'form_fields'   => [
                    $this->drawHtml('small_text', 'القضاء', 'qada', $m->qada, null, '', 'col-md-6 required'),
                    $this->drawHtml('small_text', 'اسم البلدة / القرية', 'village_name', $m->village_name, null, '', 'col-md-6 required'),
                    $this->drawHtml('small_text', 'اسم الحي', 'neighborhood', $m->neighborhood, null, '', 'col-md-6'),
                    $this->drawHtml('small_text', 'الاسم الكامل', 'full_name', $m->full_name, null, '', 'col-md-6 required'),
                    $this->drawHtml('select-box', 'المنصب', 'position', $m->position,
                        ['مختار' => 'مختار', 'عضو اختياري' => 'عضو اختياري', 'متوفي' => 'متوفي'], '', 'col-md-6 required'),
                    $this->drawHtml('small_text', 'رقم الهاتف', 'phone', $m->phone, null, '', 'col-md-6'),
                    $this->drawHtml('select-box', 'منتسب', 'is_mountasib', $m->is_mountasib ? '1' : '0',
                        ['0' => 'لا', '1' => 'نعم (أورانج)'], '', 'col-md-6'),
                    $this->drawHtml('small_text', 'الترتيب', 'sort_order', $m->sort_order, null, '', 'col-md-6'),
                ],
            ]],
        ]);
    }

    public function update(Request $request, $id)
    {
        $m = Mukhtar::findOrFail($id);

        $request->validate([
            'qada'         => 'required|string|max:191',
            'village_name' => 'required|string|max:191',
            'full_name'    => 'required|string|max:191',
            'position'     => 'required|in:مختار,عضو اختياري,متوفي',
        ]);

        $m->update([
            'qada'         => $request->qada,
            'village_name' => $request->village_name,
            'neighborhood' => $request->neighborhood ?: null,
            'full_name'    => $request->full_name,
            'position'     => $request->position,
            'phone'        => $request->phone ?: null,
            'is_mountasib' => $request->boolean('is_mountasib'),
            'sort_order'   => (int) $request->sort_order,
        ]);

        return redirect()->route('admin.mukhtars.index')
            ->with('message', 'تم التعديل بنجاح');
    }

    public function destroy($id)
    {
        Mukhtar::findOrFail($id)->delete();
        return back()->with('message', 'تم الحذف بنجاح');
    }

    public function downloadTemplate()
    {
        return Excel::download(new MukhtarsTemplateExport(), 'mukhtars_template.xlsx');
    }

    public function importForm()
    {
        return view('cms.mukhtars.import')->with([
            'layout'    => 'layouts.cms',
            'pageTitle' => 'استيراد بيانات المخاتير من Excel',
        ]);
    }

    public function importStore(Request $request)
    {
        $request->validate(['file' => 'required|mimes:xlsx,xls|max:20480']);
        $path = $request->file('file')->getPathname();
        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\MukhtarsImport(), $path);
        $count = Mukhtar::count();
        return redirect()->route('admin.mukhtars.index')
            ->with('message', "تم الاستيراد بنجاح — $count سجل");
    }
}
