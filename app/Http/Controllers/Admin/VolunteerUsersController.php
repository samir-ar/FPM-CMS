<?php

namespace App\Http\Controllers\Admin;

use DataTables;
use App\Volunteer;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;

class VolunteerUsersController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {

        if($request->ajax()) {


            $data = Volunteer::find(request('volunteer_id'))->users;

            return DataTables::of($data)
                ->addColumn('position', function($row){
                    return Volunteer::find(request('volunteer_id'))->title;
                })

                ->addColumn('date', function($row){
                    return $row->created_at;
                })
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Volunteers',
            'table_title' => '',
            'slug'		=> 'Poll',
            //'custom_btn' => "<a href='" . route('admin.userPolls.create') ."' class='btn btn-primary'></a>",
            'headers'	=> ['id', 'FPM ID', 'Name', 'Phone Number', 'Position', 'Created At'],
            'action' => route('admin.volunteerUsers.index').'?volunteer_id='.request('volunteer_id'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'member_id', 'name'=> 'member_id'],
                ['data' =>  'name', 'name'=> 'name'],
                ['data' =>  'phone_number', 'name'=> 'phone_number'],
                ['data' =>  'position', 'name'=> 'position'],
                ['data' =>  'date', 'name'=> 'date', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }
}
