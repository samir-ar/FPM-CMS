<?php

namespace App\Http\Controllers\Admin;

use DataTables;
use App\V2\BusinessType;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;

class BusinessTypesController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = BusinessType::query();

            return DataTables::of($data)
                ->addColumn('name_ar', function ($row) {
                    return $row->getTranslation('name', 'ar');
                })
                ->addColumn('name_en', function ($row) {
                    return $row->getTranslation('name', 'en');
                })
                ->addColumn('my_icon', function ($row) {
                    return $row->icon ? $this->drawImage('images/business_type_icons/' . $row->icon, '50') : 'N/A';
                })
                ->addColumn('action', function ($row) {
                    return "<a class='edit-link' href='" . route('admin.business-types.edit', $row->id) . "'>" .
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>' .
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" . route('admin.business-types.destroy', $row->id) . "'>" .
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'name_ar', 'name_en', 'my_icon', 'action'])
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'Business Types',
            'table_title' => '',
            'slug' => 'business-type',
            'custom_btn' => "<a href='" . route('admin.business-types.create') . "' class='btn btn-primary'>Add Business Type</a>",
            'headers' => ['id', 'Name (Arabic)', 'Name (English)', 'Icon', 'Action'],
            'action' => route('admin.business-types.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' => 'name_ar', 'name' => 'name'],
                ['data' => 'name_en', 'name' => 'name'],
                ['data' => 'my_icon', 'name' => 'my_icon', 'searchable' => false, 'sortable' => false],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    public function create(Request $request)
    {
        return view('components.form')->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'Add Business Type',
            'method' => 'post',
            'form_action' => route('admin.business-types.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => '',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Name (Arabic)', 'name_ar', $request->old('name_ar'), null, '', 'col-md-12 required right-to-left'),
                        $this->drawHtml('small_text', 'Name (English)', 'name_en', $request->old('name_en'), null, '', 'col-md-12 required'),
                        $this->drawHtml('image', 'Icon', 'icon', null, null, '', 'col-md-12'),
                    ],
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name_ar' => 'required',
            'name_en' => 'required',
        ]);

        $type = new BusinessType();
        $type->setTranslations('name', [
            'ar' => request('name_ar'),
            'en' => request('name_en'),
        ]);

        if (request('icon')) {
            $type->icon = $this->moveFile(request('icon'), 'images/business_type_icons');
        }

        $type->save();

        return redirect()->route('admin.business-types.index')->with('message', 'Business Type Created');
    }

    public function edit($id)
    {
        $type = BusinessType::find($id);

        return view('components.form')->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'Edit Business Type',
            'method' => 'update',
            'form_action' => route('admin.business-types.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => '',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Name (Arabic)', 'name_ar', $type->getTranslation('name', 'ar'), null, '', 'col-md-12 required right-to-left'),
                        $this->drawHtml('small_text', 'Name (English)', 'name_en', $type->getTranslation('name', 'en'), null, '', 'col-md-12 required'),
                        $this->drawHtml('image', 'Icon', 'icon', $type->icon ? 'images/business_type_icons/' . $type->icon : null, null, '', 'col-md-12'),
                    ],
                ],
            ],
        ]);
    }

    public function update($id, Request $request)
    {
        $this->validate($request, [
            'name_ar' => 'required',
            'name_en' => 'required',
        ]);

        $type = BusinessType::find($id);
        $type->setTranslations('name', [
            'ar' => request('name_ar'),
            'en' => request('name_en'),
        ]);

        if (request('icon')) {
            $this->removeFile('images/business_type_icons/' . $type->icon);
            $type->icon = $this->moveFile(request('icon'), 'images/business_type_icons');
        }

        $type->save();

        return redirect()->route('admin.business-types.index')->with('message', 'Business Type Updated');
    }

    public function destroy($id)
    {
        BusinessType::find($id)->delete();

        return back()->with('message', 'Business Type Deleted Successfully');
    }
}
