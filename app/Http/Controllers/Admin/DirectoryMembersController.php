<?php

namespace App\Http\Controllers\Admin;

use DataTables;
use App\V2\BusinessType;
use App\V2\DirectoryMember;
use App\Imports\DirectoryMembersImport;
use App\Exports\DirectoryMembersTemplateExport;
use App\Services\GeocodingService;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;

class DirectoryMembersController extends Controller
{
    use FormTrait;
    use FileTrait;

    private function geocodeMember(DirectoryMember $member)
    {
        $geocoder = new GeocodingService();
        $coords = $geocoder->geocodeParts($member->town, $member->district, $member->governorate, $member->country);

        $member->latitude = $coords['lat'] ?? null;
        $member->longitude = $coords['lng'] ?? null;
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = DirectoryMember::with('businessType');

            return DataTables::of($data)
                ->addColumn('my_image', function ($row) {
                    return $row->image ? $this->drawImage('images/directory_members/' . $row->image, '50') : 'N/A';
                })
                ->addColumn('business_type', function ($row) {
                    return $row->businessType->name ?? 'N/A';
                })
                ->addColumn('action', function ($row) {
                    return "<a class='edit-link' href='" . route('admin.directory-members.edit', $row->id) . "'>" .
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>' .
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" . route('admin.directory-members.destroy', $row->id) . "'>" .
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'my_image', 'name', 'business_type', 'phone', 'specialty', 'country', 'governorate', 'district', 'town', 'syndicate_number', 'order', 'action'])
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'Directory Members',
            'table_title' => '',
            'slug' => 'directory-member',
            'custom_btn' =>
                "<a href='" . route('admin.directory-members.create') . "' class='btn btn-primary' style='margin-right:6px'>Add Person</a>" .
                "<a href='" . route('admin.directory-members.import-form') . "' class='btn btn-success'>استيراد Excel</a>",
            'headers' => ['id', 'Image', 'Name', 'Category', 'Specialty', 'Country', 'Governorate', 'District', 'Town', 'Syndicate #', 'Phone', 'Order', 'Action'],
            'action' => route('admin.directory-members.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' => 'my_image', 'name' => 'my_image', 'searchable' => false, 'sortable' => false],
                ['data' => 'name', 'name' => 'name'],
                ['data' => 'business_type', 'name' => 'business_type'],
                ['data' => 'specialty', 'name' => 'specialty'],
                ['data' => 'country', 'name' => 'country'],
                ['data' => 'governorate', 'name' => 'governorate'],
                ['data' => 'district', 'name' => 'district'],
                ['data' => 'town', 'name' => 'town'],
                ['data' => 'syndicate_number', 'name' => 'syndicate_number'],
                ['data' => 'phone', 'name' => 'phone'],
                ['data' => 'order', 'name' => 'order'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    public function create(Request $request)
    {
        return view('components.form')->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'Add Person',
            'method' => 'post',
            'form_action' => route('admin.directory-members.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Name', 'name', $request->old('name'), null, '', 'col-md-6 required'),
                        $this->drawHtml('select-box', 'Category', 'business_type_id', '', BusinessType::all()->pluck('name', 'id'), '', 'col-md-6 required'),

                        $this->drawHtml('small_text', 'Specialty', 'specialty', $request->old('specialty'), null, '', 'col-md-6'),
                        $this->drawHtml('small_text', 'Phone', 'phone', $request->old('phone'), null, '', 'col-md-6'),

                        $this->drawHtml('small_text', 'Country', 'country', $request->old('country'), null, '', 'col-md-4'),
                        $this->drawHtml('small_text', 'Governorate', 'governorate', $request->old('governorate'), null, '', 'col-md-4'),
                        $this->drawHtml('small_text', 'District', 'district', $request->old('district'), null, '', 'col-md-4'),
                        $this->drawHtml('small_text', 'Town', 'town', $request->old('town'), null, '', 'col-md-4'),
                        $this->drawHtml('small_text', 'Syndicate Number (internal record only)', 'syndicate_number', $request->old('syndicate_number'), null, '', 'col-md-6'),

                        $this->drawHtml('image', 'Photo', 'image', null, null, '', 'col-md-12'),
                        $this->drawHtml('number', 'Order', 'order', $request->old('order'), null, '', 'col-md-12'),
                    ],
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'business_type_id' => 'required',
        ]);

        $member = new DirectoryMember();
        $member->name = request('name');
        $member->business_type_id = request('business_type_id');
        $member->specialty = request('specialty');
        $member->phone = request('phone');
        $member->country = request('country');
        $member->governorate = request('governorate');
        $member->district = request('district');
        $member->town = request('town');
        $member->syndicate_number = request('syndicate_number');
        $member->order = request('order') !== null && request('order') !== ''
            ? request('order')
            : (DirectoryMember::max('order') ?? 0) + 1;

        if (request('image')) {
            $member->image = $this->moveFile(request('image'), 'images/directory_members');
        }

        $this->geocodeMember($member);
        $member->save();

        return redirect()->route('admin.directory-members.index')->with('message', 'Person Added Successfully');
    }

    public function edit($id)
    {
        $member = DirectoryMember::find($id);

        return view('components.form')->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'Edit Person',
            'method' => 'update',
            'form_action' => route('admin.directory-members.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Name', 'name', $member->name, null, '', 'col-md-6 required'),
                        $this->drawHtml('select-box', 'Category', 'business_type_id', $member->business_type_id, BusinessType::all()->pluck('name', 'id'), '', 'col-md-6 required'),

                        $this->drawHtml('small_text', 'Specialty', 'specialty', $member->specialty, null, '', 'col-md-6'),
                        $this->drawHtml('small_text', 'Phone', 'phone', $member->phone, null, '', 'col-md-6'),

                        $this->drawHtml('small_text', 'Country', 'country', $member->country, null, '', 'col-md-4'),
                        $this->drawHtml('small_text', 'Governorate', 'governorate', $member->governorate, null, '', 'col-md-4'),
                        $this->drawHtml('small_text', 'District', 'district', $member->district, null, '', 'col-md-4'),
                        $this->drawHtml('small_text', 'Town', 'town', $member->town, null, '', 'col-md-4'),
                        $this->drawHtml('small_text', 'Syndicate Number (internal record only)', 'syndicate_number', $member->syndicate_number, null, '', 'col-md-6'),

                        $this->drawHtml('image', 'Photo', 'image', $member->image ? 'images/directory_members/' . $member->image : null, null, '', 'col-md-12'),
                        $this->drawHtml('number', 'Order', 'order', $member->order, null, '', 'col-md-12'),
                    ],
                ],
            ],
        ]);
    }

    public function update($id, Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'business_type_id' => 'required',
        ]);

        $member = DirectoryMember::find($id);
        $locationChanged = $member->country !== request('country')
            || $member->governorate !== request('governorate')
            || $member->district !== request('district')
            || $member->town !== request('town');

        $member->name = request('name');
        $member->business_type_id = request('business_type_id');
        $member->specialty = request('specialty');
        $member->phone = request('phone');
        $member->country = request('country');
        $member->governorate = request('governorate');
        $member->district = request('district');
        $member->town = request('town');
        $member->syndicate_number = request('syndicate_number');

        if (request('order') !== null && request('order') !== '') {
            $member->order = request('order');
        }

        if (request('image')) {
            $this->removeFile('images/directory_members/' . $member->image);
            $member->image = $this->moveFile(request('image'), 'images/directory_members');
        }

        if ($locationChanged) {
            $this->geocodeMember($member);
        }

        $member->save();

        return redirect()->route('admin.directory-members.index')->with('message', 'Person Updated Successfully');
    }

    public function destroy($id)
    {
        $member = DirectoryMember::find($id);
        $this->removeFile('images/directory_members/' . $member->image);
        $member->delete();

        return back()->with('message', 'Person Deleted Successfully');
    }

    public function downloadTemplate()
    {
        return Excel::download(new DirectoryMembersTemplateExport(), 'directory_members_template.xlsx');
    }

    public function importForm()
    {
        return view('cms.directory_members.import')->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'استيراد بيانات الفهرس من Excel',
        ]);
    }

    public function importStore(Request $request)
    {
        $request->validate(['file' => 'required|mimes:xlsx,xls|max:20480']);

        $import = new DirectoryMembersImport();
        Excel::import($import, $request->file('file')->getPathname());

        return redirect()->route('admin.directory-members.index')
            ->with('message', "تم الاستيراد — {$import->imported} تمت إضافتهم، {$import->skipped} تم تجاهلهم");
    }
}
