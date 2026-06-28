<?php

namespace App\Http\Controllers\Admin;

use App\Poll;
use DataTables;
use App\PollOption;
use App\AppUserPoll;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;

class UserPollsController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {
        if($request->ajax()) {

            $data = AppUserPoll::where('poll_id', request('poll_id'));


            return DataTables::of($data)

                ->addColumn('poll', function($row){
                    return Poll::withTrashed()->find($row->poll_id)->question;
                })

                ->addColumn('option', function($row){
                    return PollOption::withTrashed()->find($row->option_id)->option;
                })

                ->addColumn('user', function($row){
                    if($row->user){
                        return $row->user->name.'-'.$row->user->phone_number;
                    }
                    return null;
                })
                ->rawColumns(['poll', 'option', 'user'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'User Polls',
            'table_title' => '',
            'slug'		=> 'Poll',
            //'custom_btn' => "<a href='" . route('admin.userPolls.create') ."' class='btn btn-primary'></a>",
            'headers'	=> ['id', 'User', 'Option', 'Poll', 'Created At'],
            'action' => route('admin.userPolls.index').'?poll_id='.request('poll_id'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'user', 'name'=> 'user'],
                ['data' =>  'option', 'name'=> 'option'],
                ['data' =>  'poll', 'name'=> 'poll'],
                ['data' =>  'created_at', 'name'=> 'created_at'],
            ]),

        ]);
    }
}
