<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\V2\PoliticalWorkDocument;
use App\Http\Traits\FileTrait;
use App\Http\Traits\FormTrait;
use App\Http\Controllers\Controller;
use DataTables;

class PoliticalWorkController extends Controller
{
    use FileTrait;
    use FormTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = PoliticalWorkDocument::orderBy('category')->orderBy('document_date', 'desc');
            if ($request->filled('type') && array_key_exists($request->get('type'), PoliticalWorkDocument::$categories)) {
                $data->where('category', $request->get('type'));
            }
            return DataTables::of($data)
                ->addColumn('category_label', fn($row) =>
                    PoliticalWorkDocument::$categories[$row->category] ?? $row->category)
                ->addColumn('document_date_label', fn($row) =>
                    optional($row->document_date)->format('Y-m-d'))
                ->addColumn('action', fn($row) =>
                    "<a href='" . route('admin.political-work.edit', $row->id) . "' class='btn btn-xs btn-info' style='margin-right:4px'><i class='fa fa-edit'></i></a>" .
                    "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .
                    route('admin.political-work.destroy', $row->id) . "'>" .
                    "<i class='fa fa-trash' style='color:red;'></i></a>")
                ->rawColumns(['action'])
                ->make(true);
        }

        $activeType = $request->get('type');
        $filterButtons = '<div style="display:inline-block;">' .
            '<a href="' . route('admin.political-work.index') . '" class="btn btn-sm ' .
            (!$activeType ? 'btn-primary' : 'btn-default') . '" style="margin-right:6px">الكل</a>';
        foreach (PoliticalWorkDocument::$categories as $key => $label) {
            $filterButtons .= '<a href="' . route('admin.political-work.index', ['type' => $key]) . '" class="btn btn-sm ' .
                ($activeType === $key ? 'btn-primary' : 'btn-default') . '" style="margin-right:6px">' . $label . '</a>';
        }
        $filterButtons .= '</div>';

        return view('components.table_ajax')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'البيانات السياسية',
            'table_title' => '',
            'slug'        => 'political-work',
            'custom_btn'  => "<a href='" . route('admin.political-work.create') . "' class='btn btn-primary'>إضافة وثيقة</a>",
            'custom_btn1' => $filterButtons,
            'headers'     => ['id', 'Category', 'Title', 'تاريخ الوثيقة', 'Action'],
            'action'      => route('admin.political-work.index'),
            'columns'     => json_encode([
                ['data' => 'id',                   'name' => 'id'],
                ['data' => 'category_label',       'name' => 'category_label'],
                ['data' => 'title',                'name' => 'title'],
                ['data' => 'document_date_label',  'name' => 'document_date_label'],
                ['data' => 'action',               'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    public function create()
    {
        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'إضافة وثيقة — البيانات السياسية',
            'method'      => 'post',
            'form_action' => route('admin.political-work.store'),
            'boxes' => [[
                'wrapper-class' => 'col-md-12',
                'class'         => 'box-default',
                'box-header'    => 'Info',
                'form_fields'   => [
                    $this->drawHtml('select-box', 'التصنيف', 'category', null,
                        PoliticalWorkDocument::$categories, '', 'col-md-12 required'),
                    $this->drawHtml('small_text', 'العنوان', 'title', null, null, '', 'col-md-12 required'),
                    $this->drawHtml('file', 'ملف PDF', 'pdf', null, 'application/pdf', '', 'col-md-12 required'),
                    $this->drawHtml('date-picker', 'تاريخ الوثيقة', 'document_date', null, null, '', 'col-md-12 required'),
                ],
            ]],
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'category'      => 'required|in:takattol,haya_siyasiya,majlis_siyasi',
            'title'         => 'required',
            'pdf'           => 'required|mimes:pdf|max:20480',
            'document_date' => 'required|date',
        ]);

        $doc = new PoliticalWorkDocument();
        $doc->category      = request('category');
        $doc->title         = request('title');
        $doc->file_name     = $this->moveFile(request('pdf'), 'political_work');
        $doc->document_date = request('document_date');
        $doc->save();

        return redirect()->route('admin.political-work.index')
            ->with('message', 'تمت إضافة الوثيقة بنجاح');
    }

    public function edit($id)
    {
        $doc = PoliticalWorkDocument::findOrFail($id);
        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'تعديل وثيقة — البيانات السياسية',
            'method'      => 'update',
            'form_action' => route('admin.political-work.update', $id),
            'boxes' => [[
                'wrapper-class' => 'col-md-12',
                'class'         => 'box-default',
                'box-header'    => 'Info',
                'form_fields'   => [
                    $this->drawHtml('select-box', 'التصنيف', 'category', $doc->category,
                        PoliticalWorkDocument::$categories, '', 'col-md-12 required'),
                    $this->drawHtml('small_text', 'العنوان', 'title', $doc->title, null, '', 'col-md-12 required'),
                    $this->drawHtml('link', 'الملف الحالي', 'current_file', $doc->fileUrl(), $doc->file_name, '', 'col-md-12'),
                    $this->drawHtml('file', 'ملف PDF جديد (اتركه فارغاً للإبقاء على الملف الحالي)', 'pdf', null, 'application/pdf', '', 'col-md-12'),
                    $this->drawHtml('date-picker', 'تاريخ الوثيقة', 'document_date',
                        optional($doc->document_date)->format('Y-m-d'), null, '', 'col-md-12 required'),
                ],
            ]],
        ]);
    }

    public function update(Request $request, $id)
    {
        $doc = PoliticalWorkDocument::findOrFail($id);
        $this->validate($request, [
            'category'      => 'required|in:takattol,haya_siyasiya,majlis_siyasi',
            'title'         => 'required',
            'pdf'           => 'nullable|mimes:pdf|max:20480',
            'document_date' => 'required|date',
        ]);

        $doc->category      = request('category');
        $doc->title         = request('title');
        $doc->document_date = request('document_date');
        if ($request->hasFile('pdf')) {
            $this->removeFile('political_work/' . $doc->file_name);
            $doc->file_name = $this->moveFile(request('pdf'), 'political_work');
        }
        $doc->save();

        return redirect()->route('admin.political-work.index')
            ->with('message', 'تم التعديل بنجاح');
    }

    public function destroy($id)
    {
        $doc = PoliticalWorkDocument::find($id);
        if (!$doc) return back()->with('error', 'Document not found');

        $this->removeFile('political_work/' . $doc->file_name);
        $doc->delete();

        return back()->with('message', 'تم حذف الوثيقة بنجاح');
    }
}
