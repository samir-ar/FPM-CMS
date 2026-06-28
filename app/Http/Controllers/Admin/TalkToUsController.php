<?php

namespace App\Http\Controllers\Admin;

use DataTables;
use App\TalkToUs;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;

class TalkToUsController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {

        if($request->ajax()) {


            //$data = TalkToUs::latest();
            $data = TalkToUs::with("user");

            return DataTables::of($data)


                ->addColumn('user', function($row){
                    return $row->user->name;
                })


                ->addColumn('phone_number', function($row){
                    return $row->user->phone_number;
                })


                ->addColumn('action', function($row){
                    return "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.talkToUs.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'user','phone_number', 'action'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Talk To Us Messages',
            'table_title' => '',
            'slug'		=> 'Talk To Us Message',
            //'custom_btn' => "<a href='" . route('admin.talkToUs.create') ."' class='btn btn-primary'>Add Volunteer</a>",
            'headers'	=> ['id', 'User Name', 'Phone Number','Title', 'Text',  'Action'],
            'action' => route('admin.talkToUs.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'user', 'name'=> 'user.name'],
                ['data' =>  'phone_number', 'name'=> 'user.phone_number'],
                ['data' =>  'title', 'name'=> 'title'],
                ['data' =>  'text', 'name'=> 'text'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function destroy($id)
    {
        $message = TalkToUs::find($id);

        $message->delete();

        return back()->with('message', 'Message deleted');
    }
}
