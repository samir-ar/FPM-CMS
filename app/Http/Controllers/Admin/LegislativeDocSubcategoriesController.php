<?php

namespace App\Http\Controllers\Admin;

use DataTables;
use App\V2\LegislativeDocCategory;
use App\V2\LegislativeDocSubcategory;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Controllers\Controller;

class LegislativeDocSubcategoriesController extends Controller
{
    use FormTrait;

    private function categoryOptions()
    {
        return LegislativeDocCategory::orderBy('order')->get()
            ->mapWithKeys(fn($c) => [$c->id => $c->getTranslation('name', 'ar')])
            ->toArray();
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {

            $data = LegislativeDocSubcategory::with('category')
                ->where('category_id', request('category_id'));

            return DataTables::of($data)
                ->addColumn('my_name', function ($row) {
                    return $row->getTranslation('name', 'en');
                })

                ->addColumn('my_name_ar', function ($row) {
                    return $row->getTranslation('name', 'ar');
                })

                ->addColumn('action', function ($row) {
                    return "<a class='edit-link' href='" . route('admin.legislative-doc-subcategories.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" . route('admin.legislative-doc-subcategories.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'name', 'my_name_ar', 'order', 'action', 'my_name'])
                ->make(true);
        }

        $category = LegislativeDocCategory::find(request('category_id'));

        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Subcategories — ' . ($category?->getTranslation('name', 'ar') ?? ''),
            'table_title' => '',
            'slug'		=> 'Subcategory',
            'custom_btn' => "<a href='" . route('admin.legislative-doc-subcategories.create') . '?category_id=' . request('category_id') . "' class='btn btn-primary'>Add Subcategory</a>",
            'headers'	=> ['id', 'Name', 'Name (Arabic)', 'Order', 'Action'],
            'action' => route('admin.legislative-doc-subcategories.index') . '?category_id=' . request('category_id'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'my_name', 'name'=> 'name'],
                ['data' =>  'my_name_ar', 'name'=> 'name_ar'],
                ['data' => 'order', 'name' => 'order'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function create(Request $request)
    {
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Subcategory',
            'method'		=> 'post',
            'form_action'	=> route('admin.legislative-doc-subcategories.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => '',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Category', 'category_id', request('category_id'), $this->categoryOptions(), '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Name', 'name', $request->old('name') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Name(Arabic)', 'name_ar', $request->old('name_ar') , null, '', 'col-md-12 right-to-left required'),
                        $this->drawHtml('number', 'Order', 'order', $request->old('order') , null, '', 'col-md-12 '),
                    ],
                ],

            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'category_id' => 'required|exists:legislative_doc_categories,id',
            'name' => 'required',
        ]);

        $sub = new LegislativeDocSubcategory();
        $sub->category_id = request('category_id');

        $sub->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);

        if (request('order'))
            $sub->order = request('order');

        $sub->save();

        return redirect()->route('admin.legislative-doc-subcategories.index', ['category_id' => $sub->category_id])
            ->with('message', 'Subcategory Created');
    }

    public function edit($id)
    {
        $sub = LegislativeDocSubcategory::find($id);

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Edit Subcategory',
            'method'		=> 'update',
            'form_action'	=> route('admin.legislative-doc-subcategories.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => '',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Category', 'category_id', $sub->category_id, $this->categoryOptions(), '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Name', 'name', $sub->getTranslation('name', 'en') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Name(Arabic)', 'name_ar', $sub->getTranslation('name', 'ar') , null, '', 'col-md-12 right-to-left required'),
                        $this->drawHtml('number', 'Order', 'order', $sub->order , null, '', 'col-md-12 '),
                    ],
                ],

            ]
        ]);
    }

    public function update($id, Request $request)
    {
        $sub = LegislativeDocSubcategory::find($id);

        $this->validate($request, [
            'category_id' => 'required|exists:legislative_doc_categories,id',
            'name' => 'required',
        ]);

        $sub->category_id = request('category_id');

        $sub->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);

        if (request('order'))
            $sub->order = request('order');

        $sub->save();

        return redirect()->route('admin.legislative-doc-subcategories.index', ['category_id' => $sub->category_id])
            ->with('message', 'Subcategory Updated');
    }

    public function destroy($id)
    {
        $sub = LegislativeDocSubcategory::find($id);
        $categoryId = $sub->category_id;
        $sub->delete();

        return redirect()->route('admin.legislative-doc-subcategories.index', ['category_id' => $categoryId])
            ->with('message', 'Subcategory Deleted Successfully');
    }
}
