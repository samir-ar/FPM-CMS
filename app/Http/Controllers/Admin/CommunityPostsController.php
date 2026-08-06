<?php

namespace App\Http\Controllers\Admin;

use DataTables;
use App\V2\BusinessType;
use App\V2\CommunityPost;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;

class CommunityPostsController extends Controller
{
    use FormTrait;
    use FileTrait;

    private const STATUS_BADGES = [
        'pending' => '<span class="label label-warning">Pending</span>',
        'approved' => '<span class="label label-success">Approved</span>',
        'rejected' => '<span class="label label-danger">Rejected</span>',
    ];

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = CommunityPost::with('businessType')->latest();

            return DataTables::of($data)
                ->addColumn('my_image', function ($row) {
                    return $this->drawImage('images/community_posts/' . $row->image, '50');
                })
                ->addColumn('full_name', function ($row) {
                    return $row->first_name . ' ' . $row->last_name;
                })
                ->addColumn('business_type', function ($row) {
                    return $row->businessType->name ?? 'N/A';
                })
                ->addColumn('language_label', function ($row) {
                    return $row->language === 'en' ? 'English' : 'Arabic';
                })
                ->addColumn('status_label', function ($row) {
                    return self::STATUS_BADGES[$row->status] ?? $row->status;
                })
                ->addColumn('approved_at_label', function ($row) {
                    return $row->approved_at ? $row->approved_at->format('Y-m-d H:i') : '-';
                })
                ->addColumn('created_at_label', function ($row) {
                    return $row->created_at ? $row->created_at->format('Y-m-d H:i') : '-';
                })
                ->addColumn('action', function ($row) {
                    $links = "<a class='edit-link' href='" . route('admin.community-posts.edit', $row->id) . "'>" .
                        '<i class="fa fa-edit" aria-hidden="true"></i></a> ';

                    if ($row->status !== 'approved') {
                        $links .= "<a href='" . route('admin.community-posts.approve', $row->id) . "' title='Approve'>" .
                            '<i class="fa fa-check" style="color: green;" aria-hidden="true"></i></a> ';
                    }
                    if ($row->status !== 'rejected') {
                        $links .= "<a href='" . route('admin.community-posts.reject', $row->id) . "' title='Reject'>" .
                            '<i class="fa fa-ban" style="color: orange;" aria-hidden="true"></i></a> ';
                    }

                    $links .= "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" . route('admin.community-posts.destroy', $row->id) . "'>" .
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";

                    return $links;
                })
                ->rawColumns(['id', 'my_image', 'full_name', 'business_type', 'language_label', 'status_label', 'approved_at_label', 'created_at_label', 'action'])
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'Community Posts',
            'table_title' => '',
            'slug' => 'community-post',
            'custom_btn' => '',
            'headers' => ['id', 'Image', 'Name', 'Business Type', 'Language', 'Email', 'Status', 'Approved At', 'Created At', 'Action'],
            'action' => route('admin.community-posts.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' => 'my_image', 'name' => 'my_image', 'searchable' => false, 'sortable' => false],
                ['data' => 'full_name', 'name' => 'first_name'],
                ['data' => 'business_type', 'name' => 'business_type'],
                ['data' => 'language_label', 'name' => 'language'],
                ['data' => 'email', 'name' => 'email'],
                ['data' => 'status_label', 'name' => 'status'],
                ['data' => 'approved_at_label', 'name' => 'approved_at'],
                ['data' => 'created_at_label', 'name' => 'created_at'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    public function edit($id)
    {
        $post = CommunityPost::find($id);

        return view('components.form')->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'Review Community Post',
            'method' => 'update',
            'form_action' => route('admin.community-posts.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'First Name', 'first_name', $post->first_name, null, '', 'col-md-6 required'),
                        $this->drawHtml('small_text', 'Last Name', 'last_name', $post->last_name, null, '', 'col-md-6 required'),

                        $this->drawHtml('number', 'Age', 'age', $post->age, null, '', 'col-md-6'),
                        $this->drawHtml('small_text', 'Email', 'email', $post->email, null, '', 'col-md-6'),
                        $this->drawHtml('small_text', 'Phone', 'phone', $post->phone, null, '', 'col-md-6'),
                        $this->drawHtml('small_text', 'Address', 'address', $post->address, null, '', 'col-md-6'),

                        $this->drawHtml('select-box', 'Business Type', 'business_type_id', $post->business_type_id, BusinessType::all()->pluck('name', 'id'), '', 'col-md-6 required'),
                        $this->drawHtml('select-box', 'Language', 'language', $post->language, ['ar' => 'Arabic', 'en' => 'English'], '', 'col-md-6 required'),

                        $this->drawHtml('select-box', 'Status', 'status', $post->status, ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'], '', 'col-md-6 required'),

                        $this->drawHtml('text', 'Description', 'business_description', $post->business_description, null, '', 'col-md-12 no-ck'),

                        $this->drawHtml('image', 'Image', 'image', $post->image ? 'images/community_posts/' . $post->image : null, null, '', 'col-md-12'),
                    ],
                ],
            ],
        ]);
    }

    public function update($id, Request $request)
    {
        $this->validate($request, [
            'first_name' => 'required',
            'last_name' => 'required',
            'business_type_id' => 'required',
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $post = CommunityPost::find($id);
        $post->first_name = request('first_name');
        $post->last_name = request('last_name');
        $post->age = request('age');
        $post->email = request('email');
        $post->phone = request('phone');
        $post->address = request('address');
        $post->business_type_id = request('business_type_id');
        $post->language = request('language');
        $post->business_description = request('business_description');

        if (request('status') === 'approved' && $post->status !== 'approved') {
            $post->approved_at = now();
        }
        $post->status = request('status');

        if (request('image')) {
            $this->removeFile('images/community_posts/' . $post->image);
            $post->image = $this->moveFile(request('image'), 'images/community_posts');
        }

        $post->save();

        return redirect()->route('admin.community-posts.index')->with('message', 'Community Post Updated');
    }

    public function approve($id)
    {
        $post = CommunityPost::find($id);
        $post->status = 'approved';
        $post->approved_at = now();
        $post->save();

        return back()->with('message', 'Post Approved');
    }

    public function reject($id)
    {
        $post = CommunityPost::find($id);
        $post->status = 'rejected';
        $post->save();

        return back()->with('message', 'Post Rejected');
    }

    public function destroy($id)
    {
        $post = CommunityPost::find($id);
        $this->removeFile('images/community_posts/' . $post->image);
        $post->delete();

        return back()->with('message', 'Community Post Deleted Successfully');
    }
}
