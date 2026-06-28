<?php

namespace App\Http\Controllers\Admin;

use App\Http\Traits\FileTrait;
use App\Http\Traits\FormTrait;
use App\Imports\PermittedUsersNationalCouncilPollImport;
use App\V2\CouncilNationalPoll;
use App\V2\CouncilNationalPollPermission;
use App\V2\CouncilNationalPollVote;
use App\V2\Group;
use Illuminate\Http\Request;
use DataTables;
use App\Http\Controllers\Controller;
use App\V2\AppUser;
use App\V2\User;
use Maatwebsite\Excel\Facades\Excel;

class NationalCouncilPollController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {
        if($request->ajax()) {

            $data = CouncilNationalPoll::all();

            return DataTables::of($data)

                ->addColumn('title', function($row){
                    return $row->title;
                })

                ->addColumn('created_at', function($row){
                    return $row->created_at;
                })


                ->addColumn('is_published', function($row){

                    return ($row->is_published)?"<span class='text-success'>Published</span>":"<span class='text-warning'>Not Published</span>";
                })

                ->addColumn('questions', function($row){
                    return "<a href='".route('admin.national-council-poll.questions.index',$row)."' class='text-success'>Questions</a>";
                })

                ->addColumn('results', function($row){
                    return "<a href='".route('admin.national-council-poll.results',$row->id)."' class='text-success'>النتائج</a>";
                })

                ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.national-council-poll.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.national-council-poll.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>".
                        "<a title='X DELETE ALL DATA X' data-toggle='modal' style='margin-left:10px;' class='delete-link' href='".route('admin.national-council-poll.clear', $row->id) . "'><i class='fa fa-window-close-o' style='color: red;' aria-hidden='true'></i></a>";

                })

                ->rawColumns(['is_published','action','questions','results'])

                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'إستبيان مجلس الوطني',
            'table_title' => '',
            'slug'		=> 'national-council-poll',
            'custom_btn' => "<a href='" . route('admin.national-council-poll.create') ."' class='btn btn-primary'>Add National Council Poll</a>",
            'headers'	=> ['id', 'Title', 'Publish','Results','questions','Creation Date','Action'],
            'action' => route('admin.national-council-poll.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' => 'title', 'name' => 'title'],
                ['data' => 'is_published', 'name' => 'is_published'],
                ['data' => 'results', 'name' => 'results'],
                ['data' => 'questions', 'name' => 'questions'],
                ['data' => 'created_at', 'name' => 'created_at'],
                ['data' =>  'action', 'name'=> 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function edit($id, Request $request)
    {
        $groups = Group::all()->pluck('name', 'group_id')->toArray();

        $poll = CouncilNationalPoll::find($id);

        $default_groups = !$poll->groups()->get()->isempty() ? $poll->groups()->get()->pluck('group_id')->toArray() : '';

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Edit Poll',
            'method'		=> 'update',
            'form_action'	=> route('admin.national-council-poll.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title', 'title', $poll->title, null, '', 'col-md-6 required'),
                        $this->drawHtml('checkbox', 'Publish', 'is_published', $poll->is_published, $poll->is_published, '', 'col-md-6'),
                           ],
                ],
                [
                    'wrapper-class' => 'col-md-12 ',
                    'class' => 'box-primary',
                    'box-header' => 'Permissions',
                    'form_fields' => [
                        $this->drawHtml('file', 'List of permitted people', 'excel', '' , null, '', 'col-md-12 '),
                        "<p>The excel structure should be as following: | FPM ID | Weight |</p>"
                    ]
                ],[
                    'wrapper-class' => 'col-md-12 ',
                    'class' => 'box-primary',
                    'box-header' => 'Permitted',
                    'form_fields' => [
                        $this->getPermittedTable($id)
                    ]
                ],

            ]
        ]);
    }




    public function create(Request $request)
    {
        $groups = Group::all()->pluck('name', 'group_id')->toArray();

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'إضافة إستبيان مجلس وطني',
            'method'		=> 'post',
            'form_action'	=> route('admin.national-council-poll.store'),


                'boxes' => [
                    [
                        'wrapper-class' => 'col-md-12',
                        'class' => 'box-default',
                        'box-header' => 'Info',
                        'form_fields' => [
                            $this->drawHtml('small_text', 'Title', 'title', '', null, '', 'col-md-6 required'),
                            $this->drawHtml('checkbox', 'Publish', 'is_published', null, '', '', 'col-md-6'),
                        ],
                    ],  [
                        'wrapper-class' => 'col-md-12 ',
                        'class' => 'box-primary',
                        'box-header' => 'Permissions',
                        'form_fields' => [
                            $this->drawHtml('file', 'List of permitted people', 'excel', '' , null, '', 'col-md-12 '),
                            "<p>The excel structure should be as following: | FPM ID | Weight |</p>"
                        ]
                    ]

                ]

        ]);
    }

    private function getPermittedTable($pollId){
        $listPermissions = CouncilNationalPollPermission::where('poll_id',$pollId)->join('app_users','council_national_poll_permissions.member_id','=','app_users.member_id')->get();

        $html = "<table class='table table-striped table-dark'>";

        $html .="
          <thead>
    <tr>
      <th scope='col'>Memeber Id</th>
      <th scope='col'>Name</th>
      <th scope='col'>Weight</th>
    </tr>
  </thead>";
        foreach ($listPermissions as $permission){
            $html .="<tr>";
            $html .= "<td>".$permission->member_id."</td>";
            $html .= "<td>".$permission->name."</td>";
            $html .= "<td>".$permission->vote_weight."</td>";
            $html .="</tr>";
        }

        $html .= "</table>";
        return $html;
    }

    public function update($id, Request $request)
    {


        $this->validate($request, [
            'Title' => 'required_with:details',
            //'groups' => 'required',
            //'excel' => 'required|mimes:xlsx,csv,xls'
        ]);
        $poll = CouncilNationalPoll::find($id);

        $poll->is_published = (request('is_published'))?true:false;
        $poll->title  = $request->title;
        $poll->save();

        if($request->excel){
            //Delete all the already existing records
            CouncilNationalPollPermission::where('poll_id',$poll->id)->get()->each(function($user){
                $user->delete();
            });

            //Add new permitted list
            Excel::import(new PermittedUsersNationalCouncilPollImport($poll->id), $request->excel);
        }
        //$poll->groups()->sync(request('groups'));

        return redirect()->route('admin.national-council-poll.index')->with('message', 'Poll has been updated successfully');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'Title' => 'required_with:details',
            'excel' => 'required|mimes:xlsx,csv,xls'
        ]);

        $poll = new CouncilNationalPoll();

        $poll->is_published = (request('is_published'))?true:false;
        $poll->title  = $request->title;
        $poll->save();

        //Add the permitted list
        Excel::import(new PermittedUsersNationalCouncilPollImport($poll->id), $request->excel);

        return redirect()->route('admin.national-council-poll.index')->with('message', 'Poll has been created successfully');
    }


    public function destroy($id)
    {
        $poll = CouncilNationalPoll::find($id);
        $poll->delete();
        return back()->with('message', 'Poll Deleted Successfully');

    }

    public function results($id){
        
        $poll = CouncilNationalPoll::find($id);
        $votes = CouncilNationalPollVote::with('user')->where("poll_id",$id)->get();

        
        $votersCount = $votes->groupBy('user_id')->count();
        
        
        //Since each elector may have permission to multiple accounts
        /* AppUser::whereHas('nationalCouncilPollPermitted',function($query) use ($id){
            $query->where('poll_id',$id);
        })->select('member_id')->distinct()->count('member_id');*/
        
        //Update
        //Since each elector is now entered one time
        $electorsCount = CouncilNationalPollPermission::where('poll_id',$id)->count();

  
        $title = $poll->title;
        return view('council-election-votes.report',compact('poll','votes','votersCount','title','electorsCount'));
    }

    public function clear($id){
        CouncilNationalPollVote::where('poll_id',$id)->get()->each(
            function ($vote){
                $vote->delete();
            }
        );
        return redirect()->route('admin.national-council-poll.index')->with('message', 'Data has been deleted');

    }
}
