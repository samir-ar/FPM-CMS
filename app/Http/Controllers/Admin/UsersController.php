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

            $table = DataTables::of($data);
            foreach ($this->sortExpressions() as $column => $expression) {
                $table->orderColumn($column, function ($query, $direction) use ($column, $expression) {
                    $this->sortByExpression($query, $column, $expression, $direction);
                });
            }

            return $table
                ->filterColumn('phone_number', function($query, $keyword){
                    $normalized = ltrim($keyword, '+');
                    $query->whereRaw("TRIM(LEADING '+' FROM phone_number) LIKE ?", ["%{$normalized}%"]);
                })
                // Beirut time, like Last Activity; accounts registered since tracking began carry a "New" mark
                ->editColumn('created_at', function($row){
                    if (!$row->created_at) {
                        return '';
                    }
                    $new = $row->created_at->format('Y-m-d') >= AppInstallationReportExport::TRACKING_START;

                    return $row->created_at->format('Y-m-d H:i') . ($new ? " <span class='label label-info' title='Registered since 24 September'>&#10003; New</span>" : '');
                })
                ->addColumn('verified', function($row){
                    return $row->verified ? "<span class='label label-success'>Yes</span>" : "<span class='label label-default'>No</span>";
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
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i></a>";
                })
                ->rawColumns(['created_at', 'verified', 'scan_checkin', 'action'])
                ->make(true);
        }


        return view('cms.users.index', $this->pageData($request));
    }

    // Everything the App Users page shows around the table (the filters are kept in the page address).
    private function pageData(Request $request): array
    {
        $scanAccess = (string) $request->get('scan_access');
        $filters = $this->tableFilters($request);

        // the page address parameters currently in use
        $params = array_filter(['scan_access' => $scanAccess], fn($v) => $v !== '');
        foreach (self::FILTER_KEYS as $param => $key) {
            if ($filters[$key] !== '') {
                $params[$param] = $filters[$key];
            }
        }

        $scanTabs = [];
        foreach (['' => 'All', 'granted' => 'Has Scan Access', 'none' => 'No Scan Access'] as $value => $label) {
            $with = array_filter(['scan_access' => $value] + $params, fn($v) => $v !== '');
            $scanTabs[] = ['label' => $label, 'active' => $scanAccess === $value, 'url' => route('admin.users.index', $with)];
        }

        // each list only offers what exists under the ones before it: district -> its towns -> their sects -> their activity units
        $values = function (string $column, array $parents = []) {
            $query = \Illuminate\Support\Facades\DB::table('fpm_users')->whereNotNull($column)->where($column, '!=', '');
            foreach ($parents as $parentColumn => $parentValue) {
                if ($parentValue !== '') {
                    $query->where($parentColumn, $parentValue);
                }
            }
            $list = $query->distinct()->orderBy($column)->pluck($column)->all();

            return array_combine($list, $list);
        };
        $under = ['district' => $filters['district'], 'town' => $filters['town'], 'sect' => $filters['sect']];
        $dropdowns = [
            ['name' => 'f_district', 'label' => 'District', 'all' => 'All districts', 'current' => $filters['district'], 'options' => $values('district')],
            ['name' => 'f_town', 'label' => 'Town', 'all' => 'All towns', 'current' => $filters['town'], 'options' => $values('town', array_slice($under, 0, 1))],
            ['name' => 'f_sect', 'label' => 'Sect', 'all' => 'All sects', 'current' => $filters['sect'], 'options' => $values('sect', array_slice($under, 0, 2))],
            ['name' => 'f_unit', 'label' => 'Activity unit', 'all' => 'All activity units', 'current' => $filters['unit'], 'options' => $values('NashatUnit', $under)],
            ['name' => 'f_build', 'label' => 'App version', 'all' => 'All versions', 'current' => $filters['app_build'], 'options' => $this->versionOptions($filters)],
        ];

        $labels = ['f_district' => 'District', 'f_town' => 'Town', 'f_sect' => 'Sect', 'f_unit' => 'Activity unit', 'f_build' => 'App version', 'f_member' => 'Member ID', 'f_activity' => 'Activity', 'f_new' => 'Since 24 Sept'];
        $activityLabels = ['yes' => 'Opened since 24 Sept', 'no' => 'Not opened since 24 Sept'];
        $cascade = ['f_district', 'f_town', 'f_sect', 'f_unit'];
        $chips = [];
        foreach ($labels as $param => $label) {
            if (!isset($params[$param])) {
                continue;
            }
            // removing a district / town / sect also removes what was chosen under it
            $position = array_search($param, $cascade, true);
            $drop = $position === false ? [$param] : array_slice($cascade, $position);
            $value = $params[$param];
            if ($param === 'f_build') {
                $value = $value === 'none' ? 'Not known yet' : \App\Support\MemberApp::versionLabel($value);
            } elseif ($param === 'f_activity') {
                $value = $activityLabels[$value];
            } elseif ($param === 'f_new') {
                $value = 'Opened the app';
            }
            $chips[] = ['label' => $label, 'value' => $value, 'remove' => route('admin.users.index', array_diff_key($params, array_flip($drop)))];
        }

        // Installation Report: a month from Sept 2026 + the filters above -> Excel download
        $periods = [];
        $tz = config('app.timezone');
        $now = now($tz);
        for ($d = \Carbon\Carbon::create(2026, 9, 1, 0, 0, 0, $tz); $d->lte($now); $d->addMonth()) {
            $periods[$d->format('Y-m')] = $d->format('F Y');
        }

        return [
            'pageTitle' => 'APP USERS',
            'scanAccess' => $scanAccess,
            'scanTabs' => $scanTabs,
            'filters' => $filters,
            'dropdowns' => $dropdowns,
            'chips' => $chips,
            'clearUrl' => route('admin.users.index', $scanAccess !== '' ? ['scan_access' => $scanAccess] : []),
            'periods' => $periods,
            'currentPeriod' => $now->format('Y-m'),
            // Activity and the since-24-Sept tick are table-only: the report already counts installed and active by these rules
            'reportParams' => array_diff_key($params, ['scan_access' => 1, 'f_activity' => 1, 'f_new' => 1]),
            'tableOnlyFiltered' => isset($params['f_activity']) || isset($params['f_new']),
            'activityOptions' => $this->activityOptions($filters, $activityLabels),
            'ajaxUrl' => route('admin.users.index', $params),
            'headers' => ['id', 'Member ID', 'Name', 'Phone Number', 'Verified', 'Registration Date', 'Last Activity', 'Mobile', 'App version', 'District', 'Town', 'Sect', 'Sect Number', 'Position', 'Activity Unit', 'Civil Registry Unit', 'Scan Check-In', 'Action'],
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' => 'member_id', 'name' => 'member_id'],
                ['data' =>  'name', 'name'=> 'name'],
                ['data' => 'phone_number', 'name' => 'phone_number'],
                ['data' => 'verified', 'name' => 'verified'],
                ['data' => 'created_at', 'name' => 'created_at'],
                ['data' => 'last_activity', 'name' => 'last_activity', 'searchable' => false],
                ['data' => 'mobile', 'name' => 'mobile', 'searchable' => false],
                ['data' => 'app_version', 'name' => 'app_version', 'searchable' => false],
                ['data' => 'district', 'name' => 'district', 'searchable' => false],
                ['data' => 'town', 'name' => 'town', 'searchable' => false],
                ['data' => 'sect', 'name' => 'sect', 'searchable' => false],
                ['data' => 'sect_number', 'name' => 'sect_number', 'searchable' => false],
                ['data' => 'last_unit_position', 'name' => 'last_unit_position', 'searchable' => false],
                ['data' => 'nashat_unit', 'name' => 'nashat_unit', 'searchable' => false],
                ['data' => 'noufous_unit', 'name' => 'noufous_unit', 'searchable' => false],
                ['data' => 'scan_checkin', 'name' => 'scan_checkin', 'searchable' => false],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ];
    }

    // The versions in use among the accounts the other filters keep, newest first, with how many accounts are on each.
    private function versionOptions(array $filters): array
    {
        $query = $this->applyTableFilters(AppUser::query(), ['app_build' => ''] + $filters);
        $counts = $query->selectRaw('COALESCE(app_users.app_build, 0) AS build, COUNT(*) AS accounts')->groupBy('build')->orderByDesc('build')->pluck('accounts', 'build');

        $label = fn($build) => $build === 'none' ? 'Not known yet' : \App\Support\MemberApp::versionLabel($build);
        $options = [];
        foreach ($counts as $build => $accounts) {
            $key = $build ?: 'none';
            $options[$key] = $label($key) . ' - ' . number_format($accounts);
        }
        // keep the chosen one listed even when no account matches it with the other filters
        $chosen = $filters['app_build'];
        if ($chosen !== '' && !isset($options[$chosen])) {
            $options[$chosen] = $label($chosen) . ' - 0';
        }

        return $options;
    }

    // How many of the accounts the other filters keep opened the app since tracking began, and how many did not.
    private function activityOptions(array $filters, array $labels): array
    {
        $options = [];
        foreach ($labels as $value => $label) {
            $count = $this->applyTableFilters(AppUser::query(), ['activity' => $value] + $filters)->count();
            $options[$value] = $label . ' - ' . number_format($count);
        }

        return $options;
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

    // Sorting for the columns that are not plain app_users columns: each one is sorted by the same value the cell shows.
    private function sortExpressions(): array
    {
        // the member list value; $fallback is what the cell shows when the member is not in that list
        $member = function (string $column, ?string $fallback = null) {
            $sub = "(SELECT fpm_users.{$column} FROM fpm_users WHERE fpm_users.MemberId = app_users.member_id LIMIT 1)";

            return $fallback ? "COALESCE({$sub}, app_users.{$fallback})" : $sub;
        };

        return [
            'last_activity' => '(SELECT MAX(l.created_at) FROM user_activity_logs l WHERE l.app_user_id = app_users.id)',
            // own phone, else the one from the member's council vote, else from this account's poll vote (as in the cell)
            'mobile' => "COALESCE(NULLIF(app_users.device_model, ''), vote_phone.vote_model, "
                . "(SELECT p.device_model FROM users_polls p WHERE p.app_user_id = app_users.id AND p.device_model <> '' ORDER BY p.id DESC LIMIT 1))",
            'app_version' => 'NULLIF(app_users.app_build, 0)',
            'district' => $member('district', 'district'),
            'town' => $member('town', 'town'),
            'sect' => $member('sect', 'sect'),
            'sect_number' => 'CAST(NULLIF(' . $member('sect_number', 'sect_number') . ", '') AS UNSIGNED)",
            'last_unit_position' => $member('LastUnitPosition'),
            'nashat_unit' => $member('NashatUnit'),
            'noufous_unit' => $member('NoufousUnit'),
            'scan_checkin' => 'app_users.can_scan_checkin',
        ];
    }

    // Empty cells always go to the bottom, in both directions; equal values keep newest first so pages do not shuffle.
    private function sortByExpression($query, string $column, string $expression, string $direction): void
    {
        if ($column === 'mobile') {
            $votes = \Illuminate\Support\Facades\DB::table('council_national_poll_votes')
                ->selectRaw('member_id AS vote_member, MAX(device_model) AS vote_model')
                ->where('device_model', '<>', '')->groupBy('member_id');
            $query->leftJoinSub($votes, 'vote_phone', 'vote_phone.vote_member', '=', 'app_users.member_id');
        }
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        $query->select('app_users.*')->selectRaw("{$expression} AS sort_value")
            ->orderByRaw("(sort_value IS NULL OR CAST(sort_value AS CHAR) = '') ASC")
            ->orderByRaw("sort_value {$direction}")
            ->orderBy('app_users.id', 'desc');
    }

    // The filters of the App Users table. District / town / sect / activity unit read the member list (fpm_users), the same
    // place those columns come from; app version (a build number, or 'none') and member id read the account itself.
    private const FILTER_KEYS = ['f_district' => 'district', 'f_town' => 'town', 'f_sect' => 'sect', 'f_unit' => 'unit', 'f_build' => 'app_build', 'f_member' => 'member_id', 'f_activity' => 'activity', 'f_new' => 'new'];

    private function tableFilters(Request $request): array
    {
        $out = [];
        foreach (self::FILTER_KEYS as $param => $key) {
            $out[$key] = trim((string) $request->query($param, ''));
        }

        if ($out['app_build'] !== 'none' && !ctype_digit($out['app_build'])) {
            $out['app_build'] = '';
        }
        if (!in_array($out['activity'], ['yes', 'no'], true)) {
            $out['activity'] = '';
        }
        if ($out['new'] !== '1') {
            $out['new'] = '';
        }

        return $out;
    }

    private function applyTableFilters($query, array $f)
    {
        $columns = ['district' => 'district', 'town' => 'town', 'sect' => 'sect', 'unit' => 'NashatUnit'];
        $wanted = array_filter(array_intersect_key($f, $columns), fn($v) => $v !== '');
        if ($wanted) {
            $query->whereExists(function ($q) use ($wanted, $columns) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))->from('fpm_users')->whereColumn('fpm_users.MemberId', 'app_users.member_id');
                foreach ($wanted as $key => $value) {
                    $q->where('fpm_users.' . $columns[$key], $value);
                }
            });
        }
        if (($f['member_id'] ?? '') !== '') {
            $query->where('app_users.member_id', $f['member_id']);
        }
        if (($f['app_build'] ?? '') === 'none') {
            $query->where(fn($q) => $q->whereNull('app_users.app_build')->orWhere('app_users.app_build', 0));
        } elseif (($f['app_build'] ?? '') !== '') {
            $query->where('app_users.app_build', (int) $f['app_build']);
        }
        // opened the app at least one day since tracking began (the dashboard's rule), or not at all
        if (($f['activity'] ?? '') !== '') {
            $this->openedSinceTracking($query, $f['activity'] === 'yes');
        }
        // the "since 24 September" tick box: everyone seen since that day, old and newly registered accounts alike
        if (($f['new'] ?? '') === '1') {
            $this->openedSinceTracking($query, true);
        }

        return $query;
    }

    private function openedSinceTracking($query, bool $opened): void
    {
        $query->{$opened ? 'whereExists' : 'whereNotExists'}(function ($q) {
            $q->select(\Illuminate\Support\Facades\DB::raw(1))->from('user_activity_logs')
                ->whereColumn('user_activity_logs.app_user_id', 'app_users.id')
                ->where('user_activity_logs.activity_date', '>=', AppInstallationReportExport::TRACKING_START);
        });
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
