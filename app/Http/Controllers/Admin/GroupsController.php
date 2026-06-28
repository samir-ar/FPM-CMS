<?php

namespace App\Http\Controllers\Admin;

use DB;
use App\Group;
use DataTables;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;
use App\Http\Repositories\FpmApisRepository;

class GroupsController extends Controller
{

    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {
        if($request->ajax()) {


            $data = Group::latest();


            return DataTables::of($data)->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Groups',
            'table_title' => '',
            'slug'		=> 'Faq',
            'custom_btn' => "<a href='" . route('admin.groups.create') ."' class='btn btn-primary'>Refresh Groups</a>",
            'headers'	=> ['id', 'Name'],
            'action' => route('admin.groups.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'name', 'name'=> 'name'],
            ]),

        ]);
    }

    public function create(Request $request)
    {
        $fpm = new FpmApisRepository();
        $groups = $fpm->getGroups();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('groups')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        foreach($groups as $g){

            $group = new Group();

            $group->group_id = $g->GroupId;

            $group->name = $g->GroupName;

            $group->save();

        }

        return back()->with('message', 'Groups Updated');
    }
}
