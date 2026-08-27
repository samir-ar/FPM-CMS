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
use App\Exports\AppInstallationReportExport;
use App\V2\FpmUser;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class UsersController extends Controller
{
    use FormTrait;

    // fpm_users (synced nightly from TWH) is the source of truth for these
    // fields, not app_users — memoized per member_id so the 7 columns below
    // share one lookup per row instead of querying fpm_users 7 times each.
    private array $fpmUserCache = [];

    private function fpmUserFor($memberId): ?FpmUser
    {
        if (!array_key_exists($memberId, $this->fpmUserCache)) {
            $this->fpmUserCache[$memberId] = FpmUser::where('MemberId', $memberId)->first();
        }

        return $this->fpmUserCache[$memberId];
    }

    public function index(Request $request)
    {
        if($request->ajax()) {

            $data = AppUser::query();

            if ($request->get('scan_access') === 'granted') {
                $data->where('can_scan_checkin', true);
            } elseif ($request->get('scan_access') === 'none') {
                $data->where('can_scan_checkin', false);
            }

            return DataTables::of($data)
                ->filterColumn('phone_number', function($query, $keyword){
                    $normalized = ltrim($keyword, '+');
                    $query->whereRaw("TRIM(LEADING '+' FROM phone_number) LIKE ?", ["%{$normalized}%"]);
                })
                ->addColumn('date', function($row){
                    return $row->created_at?->toDateTimeString() ?? '';
                })
                ->addColumn('verified', function($row){
                    return $row->verified ? 'true' : 'false';
                })
                ->addColumn('scan_checkin', function($row){
                    return "<a href='" . route('admin.users.toggle-scan-checkin', $row->id) . "' class='btn btn-xs " . ($row->can_scan_checkin ? 'btn-warning' : 'btn-success') . "'>" .
                        ($row->can_scan_checkin ? 'Revoke Scan Access' : 'Grant Scan Access') . "</a>";
                })
                ->addColumn('district', function($row){
                    return $this->fpmUserFor($row->member_id)?->district ?? $row->district ?? '';
                })
                ->addColumn('town', function($row){
                    return $this->fpmUserFor($row->member_id)?->town ?? $row->town ?? '';
                })
                ->addColumn('sect', function($row){
                    return $this->fpmUserFor($row->member_id)?->sect ?? $row->sect ?? '';
                })
                ->addColumn('sect_number', function($row){
                    return $this->fpmUserFor($row->member_id)?->sect_number ?? $row->sect_number ?? '';
                })
                ->addColumn('last_unit_position', function($row){
                    return $this->fpmUserFor($row->member_id)?->LastUnitPosition ?? '';
                })
                ->addColumn('nashat_unit', function($row){
                    return $this->fpmUserFor($row->member_id)?->NashatUnit ?? '';
                })
                ->addColumn('noufous_unit', function($row){
                    return $this->fpmUserFor($row->member_id)?->NoufousUnit ?? '';
                })
                ->addColumn('action', function($row){
                    return //"<a class='edit-link' href='" . route('admin.users.show', $row->id) . "'>".
                        //'<i class="fa fa-eye" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.users.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['date', 'scan_checkin', 'action'])
                ->make(true);
        }


        $scanAccess = $request->get('scan_access');
        $filterBtn = function($value, $label) use ($scanAccess) {
            $active = $scanAccess === $value;
            $url = $value ? route('admin.users.index', ['scan_access' => $value]) : route('admin.users.index');
            return "<a href='{$url}' class='btn " . ($active ? 'btn-primary' : 'btn-default') . "' style='margin-right:4px'>{$label}</a>";
        };

        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'APP USERS',
            'table_title' => '',
            'slug'		=> 'Project',
            'headers'	=> ['id', 'Name', 'Phone Number', 'Verified', 'Registration Date', 'District', 'Town', 'Sect', 'Sect Number', 'Position', 'Activity Unit', 'Civil Registry Unit', 'Scan Check-In', 'Action'],
            'action' => route('admin.users.index'),
            'custom_btn' => "<a href='" . route('admin.users.export') ."' class='btn btn-success'>Export Users</a> &nbsp <a href='" . route('admin.users.import.create') ."' class='btn btn-primary'>Import Users</a> &nbsp <a href='" . route('admin.users.installation-report') ."' class='btn btn-warning'>Installation Report</a>",
            'custom_btn1' => $filterBtn(null, 'All')
                . $filterBtn('granted', 'Has Scan Access')
                . $filterBtn('none', 'No Scan Access'),
            'scan_access' => $scanAccess,

            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'name', 'name'=> 'name'],
                ['data' => 'phone_number', 'name' => 'phone_number'],
                ['data' => 'verified', 'name' => 'verified'],
                ['data' => 'created_at', 'name' => 'created_at'],
                ['data' => 'district', 'name' => 'district', 'searchable' => false, 'sortable' => false],
                ['data' => 'town', 'name' => 'town', 'searchable' => false, 'sortable' => false],
                ['data' => 'sect', 'name' => 'sect', 'searchable' => false, 'sortable' => false],
                ['data' => 'sect_number', 'name' => 'sect_number', 'searchable' => false, 'sortable' => false],
                ['data' => 'last_unit_position', 'name' => 'last_unit_position', 'searchable' => false, 'sortable' => false],
                ['data' => 'nashat_unit', 'name' => 'nashat_unit', 'searchable' => false, 'sortable' => false],
                ['data' => 'noufous_unit', 'name' => 'noufous_unit', 'searchable' => false, 'sortable' => false],
                ['data' => 'scan_checkin', 'name' => 'scan_checkin', 'searchable' => false, 'sortable' => false],
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

    public function toggleScanCheckin($id)
    {
        $user = AppUser::findOrFail($id);
        $user->can_scan_checkin = !$user->can_scan_checkin;
        $user->save();

        return back()->with('message', $user->can_scan_checkin
            ? 'Scan check-in access granted.'
            : 'Scan check-in access revoked.');
    }

    public function edit($id)
    {
        dd('edit');
    }

    public function export()
    {
        return Excel::download(new AppUsersExport, 'verified_users.xlsx');
    }

    public function installationReport()
    {
        $spreadsheet = (new AppInstallationReportExport())->build();
        $writer = new Xlsx($spreadsheet);

        $filename = 'app_installation_report_' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

}
