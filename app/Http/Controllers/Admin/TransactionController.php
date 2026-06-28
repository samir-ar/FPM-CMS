<?php

namespace App\Http\Controllers\Admin;

use DataTables;
use App\Transaction;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;

class TransactionController extends Controller
{
    use FormTrait;
    use FileTrait;


    public function index(Request $request)
    {
        if($request->ajax()) {


            $data = Transaction::query();


            return DataTables::of($data)
                ->addColumn('mapped_currency', function($row){
                    return iso_to_string($row->currency);
                })

                ->addColumn('action', function($row){
                    return //"<a class='edit-link' href='" . route('admin.transactions.edit', $row->id) . "'>".
                        //'<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.transactions.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['action','mapped_currency'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Transactions',
            'table_title' => '',
            'slug'		=> 'news',
            //'custom_btn' => "<a href='" . route('admin.transactions.create') ."' class='btn btn-primary'>Add News</a>",
            'headers'	=> ['id', 'Transaction ID', 'Completed', 'Success', 'Amount',  'Currency', 'User Name', 'Phone Number', 'Email', 'Resp Code', 'Resp Message', 'Created at'],
            'action' => route('admin.transactions.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'transaction_id', 'name'=> 'transaction_id'],
                ['data' =>  'completed', 'name'=> 'completed'],
                ['data' =>  'success', 'name'=> 'success'],
                ['data' =>  'amount', 'name'=> 'amount'],
                ['data' =>  'mapped_currency', 'name'=> 'mapped_currency'],
                ['data' =>  'user_name', 'name'=> 'user_name'],
                ['data' =>  'phone_number', 'name'=> 'phone_number'],
                ['data' =>  'email', 'name'=> 'email'],
                ['data' =>  'resp_code', 'name'=> 'resp_code'],
                ['data' =>  'resp_msg', 'name'=> 'resp_msg'],
                ['data' =>  'created_at', 'name'=> 'created_at'],
            ]),

        ]);
    }
}
