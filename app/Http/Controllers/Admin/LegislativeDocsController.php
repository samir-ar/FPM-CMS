<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\V2\LegislativeDoc;
use App\V2\LegislativeDocCategory;
use App\V2\LegislativeDocSubcategory;
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
            $data = LegislativeDoc::with(['category', 'subcategory'])
                ->orderByRaw('date IS NULL')->orderBy('date', 'desc')
                ->orderBy('order');
            return DataTables::of($data)
                ->addColumn('tab_label', function ($r) {
                    $catName = $r->category?->getTranslation('name', 'ar') ?? '—';
                    if ($r->subcategory) {
                        return $catName . ' ← ' . $r->subcategory->getTranslation('name', 'ar');
                    }
                    return $catName;
                })
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
            'custom_btn'  => "<a href='" . route('admin.legislative-docs.create') . "' class='btn btn-primary'>إضافة وثيقة</a> " .
                "<a href='" . route('admin.legislative-doc-categories.index') . "' class='btn btn-default'>إدارة التبويبات</a>",
            'headers'     => ['id', 'التبويب', 'العنوان', 'التاريخ', 'Action'],
            'action'      => route('admin.legislative-docs.index'),
            'columns'     => json_encode([
                ['data' => 'id',        'name' => 'id'],
                ['data' => 'tab_label', 'name' => 'tab_label'],
                ['data' => 'title',     'name' => 'title'],
                ['data' => 'date',      'name' => 'date'],
                ['data' => 'action',    'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    private function categoryOptions()
    {
        return LegislativeDocCategory::orderBy('order')->get()
            ->mapWithKeys(fn($c) => [$c->id => $c->getTranslation('name', 'ar')])
            ->toArray();
    }

    private function subcategoryOptions()
    {
        $options = ['' => '— بدون (يظهر ضمن التبويب الرئيسي) —'];
        return $options + LegislativeDocSubcategory::with('category')->orderBy('order')->get()
            ->mapWithKeys(function ($s) {
                $catName = $s->category?->getTranslation('name', 'ar') ?? '';
                return [$s->id => $catName . ' ← ' . $s->getTranslation('name', 'ar')];
            })
            ->toArray();
    }

    // A subcategory, when chosen, must actually belong to the chosen category —
    // there's no cascading-dropdown JS here, so this is enforced server-side.
    private function validateSubcategoryBelongsToCategory(Request $request)
    {
        if (!$request->subcategory_id) return;
        $sub = LegislativeDocSubcategory::find($request->subcategory_id);
        if (!$sub || $sub->category_id != $request->category_id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'subcategory_id' => 'التبويب الفرعي المختار لا يتبع للتبويب الرئيسي المختار',
            ]);
        }
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
                    $this->drawHtml('select-box', 'التبويب الرئيسي', 'category_id', null, $this->categoryOptions(), '', 'col-md-12 required'),
                    $this->drawHtml('select-box', 'التبويب الفرعي (اختياري)', 'subcategory_id', null, $this->subcategoryOptions(), '', 'col-md-12'),
                    $this->drawHtml('small_text', 'العنوان', 'title', null, null, '', 'col-md-12 required'),
                    $this->drawHtml('date-picker', 'التاريخ', 'date', now()->toDateString(), null, '', 'col-md-12 required'),
                    $this->drawHtml('file', 'ملف PDF', 'pdf', null, 'application/pdf', '', 'col-md-12 required'),
                    $this->drawHtml('small_text', 'الترتيب', 'order', '0', null, '', 'col-md-6'),
                ],
            ]],
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'category_id' => 'required|exists:legislative_doc_categories,id',
            'subcategory_id' => 'nullable|exists:legislative_doc_subcategories,id',
            'title' => 'required',
            'date'  => 'required|date',
            'pdf'   => 'required|mimes:pdf|max:20480',
            'order' => 'nullable|integer',
        ]);
        $this->validateSubcategoryBelongsToCategory($request);

        $doc = new LegislativeDoc();
        $doc->category_id = $request->category_id;
        $doc->subcategory_id = $request->subcategory_id ?: null;
        $doc->title     = $request->title;
        $doc->date      = \Carbon\Carbon::parse($request->date)->toDateString();
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
                    $this->drawHtml('select-box', 'التبويب الرئيسي', 'category_id', $doc->category_id, $this->categoryOptions(), '', 'col-md-12 required'),
                    $this->drawHtml('select-box', 'التبويب الفرعي (اختياري)', 'subcategory_id', $doc->subcategory_id, $this->subcategoryOptions(), '', 'col-md-12'),
                    $this->drawHtml('small_text', 'العنوان', 'title', $doc->title, null, '', 'col-md-12 required'),
                    $this->drawHtml('date-picker', 'التاريخ', 'date', $doc->date, null, '', 'col-md-12 required'),
                    $this->drawHtml('link', 'الملف الحالي', 'current_pdf', \Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/storage/legislative_docs/' . $doc->file_name), 'عرض الملف', 'col-md-12'),
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
            'category_id' => 'required|exists:legislative_doc_categories,id',
            'subcategory_id' => 'nullable|exists:legislative_doc_subcategories,id',
            'title' => 'required',
            'date'  => 'required|date',
            'pdf'   => 'nullable|mimes:pdf|max:20480',
            'order' => 'nullable|integer',
        ]);
        $this->validateSubcategoryBelongsToCategory($request);
        $doc->category_id = $request->category_id;
        $doc->subcategory_id = $request->subcategory_id ?: null;
        $doc->title = $request->title;
        $doc->date  = \Carbon\Carbon::parse($request->date)->toDateString();
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
