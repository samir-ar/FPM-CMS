<?php

namespace App\Http\Controllers\Admin;

use DataTables;
use App\AppUser;
use App\User;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UsersImport;
use App\Exports\AppUsersExport;

class UsersController extends Controller
{
    use FormTrait;

    public function index(Request $request)
    {
        if($request->ajax()) {

            $data = AppUser::query();

            return DataTables::of($data)
                ->addColumn('phone_number', function($row){
                    return $row->phone_number;
                })
                ->addColumn('email', function($row){
                    return $row->email;
                })
                ->addColumn('date', function($row){
                    return $row->created_at?->toDateTimeString() ?? '';
                })
                ->addColumn('verified', function($row){
                    return $row->verified ? 'true' : 'false';
                })
                ->addColumn('action', function($row){
                    return //"<a class='edit-link' href='" . route('admin.users.show', $row->id) . "'>".
                        //'<i class="fa fa-eye" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.users.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['phone_number', 'email', 'date','action'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Members',
            'table_title' => '',
            'slug'		=> 'Project',
            'headers'	=> ['id', 'Name', 'Phone Number', 'Verified', 'Registration Date', 'Action'],
            'action' => route('admin.users.index'),
            'custom_btn' => "<a href='" . route('admin.users.export') ."' class='btn btn-success'>Export Users</a> &nbsp <a href='" . route('admin.users.import.create') ."' class='btn btn-primary'>Import Users</a>",

            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'name', 'name'=> 'name'],
                ['data' => 'phone_number', 'name' => 'phone_number'],
                ['data' => 'verified', 'name' => 'verified'],
                ['data' => 'created_at', 'name' => 'created_at'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }




    public function importCreate(Request $request){

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Import Users',
            'method'		=> 'post',
            'form_action'	=> route('admin.users.import.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12 ',
                    'class' => 'box-primary',
                    'box-header' => 'Import users',
                    'form_fields' => [
                        $this->drawHtml('file', 'List Not installed Users', 'excel', '' , null, '', 'col-md-12 '),
                        "<p>The excel structure should be as following: | FPM ID | Full Name | Phone Number (+96103...)</p>"
                    ]
                ]
            ]
        ]);
    }

    // public function qr_code(Request $request){
    //     // $users = AppUser::all()->where('qr_code',null)->pluck('name','id')->toArray();
    //     $users = AppUser::all()->pluck('name','id')->toArray();

    //     return view('components.form')->with([
    //         'layout'         => 'layouts.cms',
    //         'pageTitle'		=> 'QR Code',
    //         'method'		=> 'post',
    //         'form_action'	=> route('admin.users.qr-code.store'),

    //         'boxes' => [
    //             [
    //                 'wrapper-class' => 'col-md-12 ',
    //                 'class' => 'box-primary',
    //                 'box-header' => 'QR Code',
    //                 'form_fields' => [
    //                     $this->drawHtml('select-box', 'Users', 'user_id', $request->old('qr-code'), $users, '', 'col-md-12 '),
    //                     $this->drawHtml('file', 'QR Code Image', 'qr_code', '' , null, '', 'col-md-12 '),
    //                 ]
    //             ]
    //         ]
    //     ]);
    // }

    // public function qr_code_store(Request $request)
    // {
    //     $this->validate($request, [
    //         'qr_code' => 'required|mimes:png,jpg,jpeg,svg'
    //     ]);

    //     $users = AppUser::find($request->user_id);
    //     $users->qr_code = $request->qr_code;
    //     if($request->qr_code){
    //         $users->qr_code = parent::store_file(AppUser::$IMAGE_PATH,$request->qr_code);
    //     }
    //     $users->update();

    //     return redirect()->route('admin.users.index')->with('message', 'Users has been imported successfully');
    // }

    public function importStore(Request $request)
    {
        $this->validate($request, [
            'excel' => 'required|mimes:xlsx,csv,xls'
        ]);

        //Add the permitted list
        Excel::import(new UsersImport(), $request->excel);

        return redirect()->route('admin.users.index')->with('message', 'Users has been imported successfully');
    }

    public function destroy($id)
    {
        AppUser::find($id)->delete();

        return back()->with('message', 'User Deleted.');
    }

    public function edit($id)
    {
        dd('edit');
    }

    public function export()
    {
        return Excel::download(new AppUsersExport, 'verified_users.xlsx');
    }

}
