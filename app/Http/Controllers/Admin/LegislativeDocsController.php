<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\V2\LegislativeDoc;
use App\Http\Traits\FileTrait;
use App\Http\Traits\FormTrait;
use App\Http\Controllers\Controller;
use DataTables;

class LegislativeDocsController extends Controller
{
    use FileTrait, FormTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = LegislativeDoc::orderBy('tab')->orderBy('order');
            return DataTables::of($data)
                ->addColumn('tab_label', fn($r) => LegislativeDoc::$tabs[$r->tab] ?? $r->tab)
                ->addColumn('action', fn($r) =>
                    "<a href='" . route('admin.legislative-docs.edit', $r->id) . "' class='btn btn-xs btn-info' style='margin-right:4px'><i class='fa fa-edit'></i></a>" .
                    "<a data-toggle='modal' class='delete-link btn btn-xs btn-danger' href='#deleteModal' id='" .
                    route('admin.legislative-docs.destroy', $r->id) . "'><i class='fa fa-trash'></i></a>")
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'العمل التشريعي',
            'table_title' => '',
            'slug'        => 'legislative-docs',
            'custom_btn'  => "<a href='" . route('admin.legislative-docs.create') . "' class='btn btn-primary'>إضافة وثيقة</a>",
            'headers'     => ['id', 'التبويب', 'العنوان', 'Action'],
            'action'      => route('admin.legislative-docs.index'),
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
            'pageTitle'   => 'إضافة وثيقة — العمل التشريعي',
            'method'      => 'post',
            'form_action' => route('admin.legislative-docs.store'),
            'boxes' => [[
                'wrapper-class' => 'col-md-12',
                'class'         => 'box-default',
                'box-header'    => 'Info',
                'form_fields'   => [
                    $this->drawHtml('select-box', 'التبويب', 'tab', null, LegislativeDoc::$tabs, '', 'col-md-12 required'),
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
            'tab'   => 'required|in:sawdir,iqtirahaat',
            'title' => 'required',
            'pdf'   => 'required|mimes:pdf|max:20480',
            'order' => 'nullable|integer',
        ]);

        $doc = new LegislativeDoc();
        $doc->tab       = $request->tab;
        $doc->title     = $request->title;
        $doc->file_name = $this->moveFile($request->file('pdf'), 'legislative_docs');
        $doc->order     = $request->order ?? 0;
        $doc->save();

        return redirect()->route('admin.legislative-docs.index')
            ->with('message', 'تمت إضافة الوثيقة بنجاح');
    }

    public function edit($id)
    {
        $doc = LegislativeDoc::findOrFail($id);
        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'تعديل وثيقة — العمل التشريعي',
            'method'      => 'update',
            'form_action' => route('admin.legislative-docs.update', $id),
            'boxes' => [[
                'wrapper-class' => 'col-md-12',
                'class'         => 'box-default',
                'box-header'    => 'Info',
                'form_fields'   => [
                    $this->drawHtml('select-box', 'التبويب', 'tab', $doc->tab, LegislativeDoc::$tabs, '', 'col-md-12 required'),
                    $this->drawHtml('small_text', 'العنوان', 'title', $doc->title, null, '', 'col-md-12 required'),
                    $this->drawHtml('file', 'ملف PDF جديد', 'pdf', null, 'application/pdf', '', 'col-md-12'),
                    $this->drawHtml('small_text', 'الترتيب', 'order', $doc->order, null, '', 'col-md-6'),
                ],
            ]],
        ]);
    }

    public function update(Request $request, $id)
    {
        $doc = LegislativeDoc::findOrFail($id);
        $this->validate($request, [
            'tab'   => 'required|in:sawdir,iqtirahaat',
            'title' => 'required',
            'pdf'   => 'nullable|mimes:pdf|max:20480',
            'order' => 'nullable|integer',
        ]);
        $doc->tab   = $request->tab;
        $doc->title = $request->title;
        $doc->order = $request->order ?? 0;
        if ($request->hasFile('pdf')) {
            $this->removeFile('legislative_docs/' . $doc->file_name);
            $doc->file_name = $this->moveFile($request->file('pdf'), 'legislative_docs');
        }
        $doc->save();
        return redirect()->route('admin.legislative-docs.index')->with('message', 'تم التعديل بنجاح');
    }

    public function destroy($id)
    {
        $doc = LegislativeDoc::findOrFail($id);
        $this->removeFile('legislative_docs/' . $doc->file_name);
        $doc->delete();
        return back()->with('message', 'تم الحذف بنجاح');
    }
}
