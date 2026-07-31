<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\V2\MunicipalityMember;
use App\Http\Traits\FormTrait;
use App\Http\Controllers\Controller;
use DataTables;

class MunicipalityMembersController extends Controller
{
    use FormTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = MunicipalityMember::query()->orderBy('qada')->orderBy('municipality_name')->orderBy('sort_order');
            return DataTables::of($data)
                ->addColumn('mountasib_badge', fn($row) =>
                    $row->is_mountasib
                        ? "<span class='badge' style='background:#f39c12;color:#fff;'>منتسب</span>"
                        : '')
                ->addColumn('action', fn($row) =>
                    "<a href='" . route('admin.municipality-members.edit', $row->id) . "' class='btn btn-xs btn-info' style='margin-right:4px'><i class='fa fa-edit'></i></a>" .
                    "<a data-toggle='modal' class='delete-link btn btn-xs btn-danger' href='#deleteModal' id='" .
                    route('admin.municipality-members.destroy', $row->id) . "'><i class='fa fa-trash'></i></a>")
                ->rawColumns(['mountasib_badge', 'action'])
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'بلديات / مخاتير — الأعضاء',
            'table_title' => '',
            'slug'        => 'municipality-members',
            'custom_btn'  =>
                "<a href='" . route('admin.municipality-members.create') . "' class='btn btn-primary' style='margin-right:6px'>إضافة عضو</a>" .
                "<a href='" . route('admin.municipality-members.import-form') . "' class='btn btn-success'>استيراد Excel</a>",
            'headers'     => ['#', 'القضاء', 'البلدية', 'القرية', 'الاسم', 'المنصب', 'منتسب', 'Action'],
            'action'      => route('admin.municipality-members.index'),
            'columns'     => json_encode([
                ['data' => 'id',              'name' => 'id'],
                ['data' => 'qada',            'name' => 'qada'],
                ['data' => 'municipality_name','name' => 'municipality_name'],
                ['data' => 'village_name',    'name' => 'village_name'],
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
            'pageTitle'   => 'إضافة عضو بلدية',
            'method'      => 'post',
            'form_action' => route('admin.municipality-members.store'),
            'boxes' => [[
                'wrapper-class' => 'col-md-12',
                'class'         => 'box-default',
                'box-header'    => 'معلومات العضو',
                'form_fields'   => [
                    $this->drawHtml('small_text', 'القضاء', 'qada', null, null, '', 'col-md-6 required'),
                    $this->drawHtml('small_text', 'اسم البلدية', 'municipality_name', null, null, '', 'col-md-6 required'),
                    $this->drawHtml('small_text', 'اسم القرية', 'village_name', null, null, '', 'col-md-6'),
                    $this->drawHtml('small_text', 'الاسم الكامل', 'full_name', null, null, '', 'col-md-6 required'),
                    $this->drawHtml('select-box', 'المنصب', 'position', null,
                        ['رئيس' => 'رئيس', 'نائب رئيس' => 'نائب رئيس', 'عضو' => 'عضو'], '', 'col-md-6 required'),
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
            'qada'              => 'required|string|max:191',
            'municipality_name' => 'required|string|max:191',
            'full_name'         => 'required|string|max:191',
            'position'          => 'required|in:رئيس,نائب رئيس,عضو',
        ]);

        MunicipalityMember::create([
            'qada'              => $request->qada,
            'municipality_name' => $request->municipality_name,
            'village_name'      => $request->village_name ?: null,
            'full_name'         => $request->full_name,
            'position'          => $request->position,
            'phone'             => $request->phone ?: null,
            'is_mountasib'      => $request->boolean('is_mountasib'),
            'sort_order'        => (int) $request->sort_order,
        ]);

        return redirect()->route('admin.municipality-members.index')
            ->with('message', 'تمت إضافة العضو بنجاح');
    }

    public function edit($id)
    {
        $member = MunicipalityMember::findOrFail($id);

        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'تعديل عضو بلدية',
            'method'      => 'update',
            'form_action' => route('admin.municipality-members.update', $id),
            'boxes' => [[
                'wrapper-class' => 'col-md-12',
                'class'         => 'box-default',
                'box-header'    => 'معلومات العضو',
                'form_fields'   => [
                    $this->drawHtml('small_text', 'القضاء', 'qada', $member->qada, null, '', 'col-md-6 required'),
                    $this->drawHtml('small_text', 'اسم البلدية', 'municipality_name', $member->municipality_name, null, '', 'col-md-6 required'),
                    $this->drawHtml('small_text', 'اسم القرية', 'village_name', $member->village_name, null, '', 'col-md-6'),
                    $this->drawHtml('small_text', 'الاسم الكامل', 'full_name', $member->full_name, null, '', 'col-md-6 required'),
                    $this->drawHtml('select-box', 'المنصب', 'position', $member->position,
                        ['رئيس' => 'رئيس', 'نائب رئيس' => 'نائب رئيس', 'عضو' => 'عضو'], '', 'col-md-6 required'),
                    $this->drawHtml('small_text', 'رقم الهاتف', 'phone', $member->phone, null, '', 'col-md-6'),
                    $this->drawHtml('select-box', 'منتسب', 'is_mountasib', $member->is_mountasib ? '1' : '0',
                        ['0' => 'لا', '1' => 'نعم (أورانج)'], '', 'col-md-6'),
                    $this->drawHtml('small_text', 'الترتيب', 'sort_order', $member->sort_order, null, '', 'col-md-6'),
                ],
            ]],
        ]);
    }

    public function update(Request $request, $id)
    {
        $member = MunicipalityMember::findOrFail($id);

        $request->validate([
            'qada'              => 'required|string|max:191',
            'municipality_name' => 'required|string|max:191',
            'full_name'         => 'required|string|max:191',
            'position'          => 'required|in:رئيس,نائب رئيس,عضو',
        ]);

        $member->update([
            'qada'              => $request->qada,
            'municipality_name' => $request->municipality_name,
            'village_name'      => $request->village_name ?: null,
            'full_name'         => $request->full_name,
            'position'          => $request->position,
            'phone'             => $request->phone ?: null,
            'is_mountasib'      => $request->boolean('is_mountasib'),
            'sort_order'        => (int) $request->sort_order,
        ]);

        return redirect()->route('admin.municipality-members.index')
            ->with('message', 'تم تعديل بيانات العضو بنجاح');
    }

    public function destroy($id)
    {
        MunicipalityMember::findOrFail($id)->delete();
        return back()->with('message', 'تم حذف العضو بنجاح');
    }

    public function importForm()
    {
        return view('cms.municipality_members.import')->with([
            'layout'    => 'layouts.cms',
            'pageTitle' => 'استيراد بيانات الأعضاء من Excel',
        ]);
    }

    public function importStore(Request $request)
    {
        $request->validate(['file' => 'required|mimes:xlsx,xls|max:20480']);

        $path = $request->file('file')->getPathname();

        \Maatwebsite\Excel\Facades\Excel::import(
            new \App\Imports\MunicipalityMembersImport(), $path
        );

        $count = MunicipalityMember::count();

        return redirect()->route('admin.municipality-members.index')
            ->with('message', "تم الاستيراد بنجاح — $count سجل");
    }
}
