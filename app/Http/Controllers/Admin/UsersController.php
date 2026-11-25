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
            $this->applyTableFilters($data, $this->tableFilters($request));

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
                // last time this account was active in the app (the activity log is refreshed on every call; stored in UTC)
                ->addColumn('last_activity', function($row){
                    $last = \Illuminate\Support\Facades\DB::table('user_activity_logs')->where('app_user_id', $row->id)->max('created_at');

                    return $last ? \Carbon\Carbon::parse($last, 'UTC')->setTimezone(config('app.timezone'))->format('Y-m-d H:i') : '';
                })
                ->addColumn('mobile', function($row){
                    if ((string) $row->device_model !== '') {
                        return \App\Support\DeviceName::pretty($row->device_model);
                    }

                    // no phone stored on this account yet: the one from the member's latest vote, when there is one
                    return \App\Support\MemberApp::forMembers([(string) $row->member_id])[(string) $row->member_id]['model'] ?? '';
                })
                ->addColumn('app_version', function($row){
                    return \App\Support\MemberApp::versionLabel($row->app_build);
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

        // the filters (kept in the page address, so a reload or the Excel button keeps them)
        $filters = $this->tableFilters($request);
        $hidden = ($scanAccess ? "<input type='hidden' name='scan_access' value='" . e($scanAccess) . "'>" : '');
        // every filter except the member id is a drop-down (searchable: type to find a value) of the values in the member list
        $dropdown = function ($name, $allLabel, $column, $current, $w) {
            $html = "<option value=''>{$allLabel}</option>";
            $values = \Illuminate\Support\Facades\DB::table('fpm_users')->whereNotNull($column)->where($column, '!=', '')->distinct()->orderBy($column)->pluck($column);
            foreach ($values as $v) {
                $html .= "<option value='" . e($v) . "'" . ($current === (string) $v ? ' selected' : '') . '>' . e($v) . '</option>';
            }

            return "<select name='{$name}' class='form-control select2' style='width:{$w}px;margin-right:4px;'>{$html}</select> ";
        };
        $filterForm = "<form method='GET' action='" . route('admin.users.index') . "' style='clear:both;padding-top:8px;'>" . $hidden
            . $dropdown('f_district', 'All districts', 'district', $filters['district'], 170)
            . $dropdown('f_town', 'All towns', 'town', $filters['town'], 170)
            . $dropdown('f_sect', 'All sects', 'sect', $filters['sect'], 150)
            . $dropdown('f_unit', 'All activity units', 'NashatUnit', $filters['unit'], 200)
            . "<input type='text' name='f_member' value='" . e($filters['member_id']) . "' placeholder='Member ID' class='form-control' style='width:130px;display:inline-block;margin-right:4px;'>"
            . "<button type='submit' class='btn btn-primary' style='margin-right:4px;'>Filter</button>"
            . "<a href='" . route('admin.users.index', $scanAccess ? ['scan_access' => $scanAccess] : []) . "' class='btn btn-default'>Reset</a></form>";

        // Installation Report: month (from Sept 2026) + the filters above -> Excel download
        $periods = '';
        $tz = config('app.timezone');
        $now = now($tz);
        for ($d = \Carbon\Carbon::create(2026, 9, 1, 0, 0, 0, $tz); $d->lte($now); $d->addMonth()) {
            $periods .= "<option value='" . $d->format('Y-m') . "'" . ($d->format('Y-m') === $now->format('Y-m') ? ' selected' : '') . '>' . $d->format('F Y') . '</option>';
        }
        $reportForm = "<form method='GET' action='" . route('admin.users.installation-report') . "' style='display:inline-block;'>"
            . "<input type='hidden' name='f_district' value='" . e($filters['district']) . "'><input type='hidden' name='f_town' value='" . e($filters['town']) . "'>"
            . "<input type='hidden' name='f_sect' value='" . e($filters['sect']) . "'><input type='hidden' name='f_unit' value='" . e($filters['unit']) . "'>"
            . "<input type='hidden' name='f_member' value='" . e($filters['member_id']) . "'>"
            . "<select name='period' class='form-control' style='width:150px;display:inline-block;'>{$periods}</select> "
            . "<button type='submit' class='btn btn-warning'>Installation Report</button></form>";

        return view('components.table_ajax')->with([
            'ajaxQuery' => http_build_query(array_filter([
                'scan_access' => $scanAccess, 'f_district' => $filters['district'], 'f_town' => $filters['town'], 'f_sect' => $filters['sect'],
                'f_unit' => $filters['unit'], 'f_member' => $filters['member_id'],
            ], fn($v) => $v !== null && $v !== '')),
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'APP USERS',
            'table_title' => '',
            'slug'		=> 'Project',
            'headers'	=> ['id', 'Member ID', 'Name', 'Phone Number', 'Verified', 'Registration Date', 'Last Activity', 'Mobile', 'App version', 'District', 'Town', 'Sect', 'Sect Number', 'Position', 'Activity Unit', 'Civil Registry Unit', 'Scan Check-In', 'Action'],
            'action' => route('admin.users.index'),
            'custom_btn' => "<a href='" . route('admin.users.export') ."' class='btn btn-success'>Export Users</a> &nbsp <a href='" . route('admin.users.import.create') ."' class='btn btn-primary'>Import Users</a> &nbsp " . $reportForm . " &nbsp <a href='" . route('admin.users.login-limits') ."' class='btn btn-default'>Login Limits</a>",
            'custom_btn1' => $filterBtn(null, 'All')
                . $filterBtn('granted', 'Has Scan Access')
                . $filterBtn('none', 'No Scan Access'),
            'custom_btn2' => $filterForm,
            'scan_access' => $scanAccess,

            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' => 'member_id', 'name' => 'member_id'],
                ['data' =>  'name', 'name'=> 'name'],
                ['data' => 'phone_number', 'name' => 'phone_number'],
                ['data' => 'verified', 'name' => 'verified'],
                ['data' => 'created_at', 'name' => 'created_at'],
                ['data' => 'last_activity', 'name' => 'last_activity', 'searchable' => false, 'sortable' => false],
                ['data' => 'mobile', 'name' => 'mobile', 'searchable' => false, 'sortable' => false],
                ['data' => 'app_version', 'name' => 'app_version', 'searchable' => false, 'sortable' => false],
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

    // The filters of the App Users table (district / town / sect / activity unit / member id). They read the member list
    // (fpm_users), the same place the table's district, town, sect and unit columns come from.
    private const FILTER_KEYS = ['f_district' => 'district', 'f_town' => 'town', 'f_sect' => 'sect', 'f_unit' => 'unit', 'f_member' => 'member_id'];

    private function tableFilters(Request $request): array
    {
        $out = [];
        foreach (self::FILTER_KEYS as $param => $key) {
            $out[$key] = trim((string) $request->query($param, ''));
        }

        return $out;
    }

    private function applyTableFilters($query, array $f)
    {
        if (array_filter($f, fn($v) => $v !== '')) {
            $query->whereExists(function ($q) use ($f) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))->from('fpm_users')->whereColumn('fpm_users.MemberId', 'app_users.member_id');
                if ($f['district'] !== '') {
                    $q->where('fpm_users.district', $f['district']);
                }
                if ($f['town'] !== '') {
                    $q->where('fpm_users.town', $f['town']);
                }
                if ($f['sect'] !== '') {
                    $q->where('fpm_users.sect', $f['sect']);
                }
                if ($f['unit'] !== '') {
                    $q->where('fpm_users.NashatUnit', $f['unit']);
                }
            });
            if ($f['member_id'] !== '') {
                $query->where('app_users.member_id', $f['member_id']);
            }
        }

        return $query;
    }

    // The Installation Report is an Excel file: pick a month (from Sept 2026, when app activity tracking began) and download.
    // The App Users filters, when set, narrow it too.
    public function installationReport(Request $request)
    {
        $tz = config('app.timezone');
        $now = now($tz);
        $start = \Carbon\Carbon::create(2026, 9, 1, 0, 0, 0, $tz);
        $period = (string) $request->query('period', $now->format('Y-m'));
        try {
            $picked = \Carbon\Carbon::createFromFormat('!Y-m', $period, $tz);
        } catch (\Throwable $e) {
            $picked = $now->copy()->startOfMonth();
        }
        if ($picked->lt($start)) {
            $picked = $start->copy();
        }
        if ($picked->gt($now)) {
            $picked = $now->copy()->startOfMonth();
        }

        $export = new AppInstallationReportExport($picked->year, $picked->month, $this->tableFilters($request));
        $writer = new Xlsx($export->build());
        $filename = 'app_installation_report_' . $picked->format('Y-m') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

}
