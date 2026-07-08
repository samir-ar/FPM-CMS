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
            $data = PoliticalWorkDocument::orderBy('category')->orderBy('order');
            return DataTables::of($data)
                ->addColumn('category_label', fn($row) =>
                    PoliticalWorkDocument::$categories[$row->category] ?? $row->category)
                ->addColumn('action', fn($row) =>
                    "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .
                    route('admin.political-work.destroy', $row->id) . "'>" .
                    "<i class='fa fa-trash' style='color:red;'></i></a>")
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'العمل السياسي',
            'table_title' => '',
            'slug'        => 'political-work',
            'custom_btn'  => "<a href='" . route('admin.political-work.create') . "' class='btn btn-primary'>إضافة وثيقة</a>",
            'headers'     => ['id', 'Category', 'Title', 'Action'],
            'action'      => route('admin.political-work.index'),
            'columns'     => json_encode([
                ['data' => 'id',             'name' => 'id'],
                ['data' => 'category_label', 'name' => 'category_label'],
                ['data' => 'title',          'name' => 'title'],
                ['data' => 'action',         'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    public function create()
    {
        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'إضافة وثيقة — العمل السياسي',
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
                    $this->drawHtml('small_text', 'الترتيب', 'order', '0', null, '', 'col-md-6'),
                ],
            ]],
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'category' => 'required|in:takattol,haya_siyasiya,majlis_siyasi',
            'title'    => 'required',
            'pdf'      => 'required|mimes:pdf|max:20480',
            'order'    => 'nullable|integer',
        ]);

        $doc = new PoliticalWorkDocument();
        $doc->category  = request('category');
        $doc->title     = request('title');
        $doc->file_name = $this->moveFile(request('pdf'), 'political_work');
        $doc->order     = request('order', 0);
        $doc->save();

        return redirect()->route('admin.political-work.index')
            ->with('message', 'تمت إضافة الوثيقة بنجاح');
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
