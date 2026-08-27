<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\V2\CheckinEvent;
use App\Http\Traits\FormTrait;
use App\Http\Controllers\Controller;
use DataTables;

class CheckinEventsController extends Controller
{
    use FormTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = CheckinEvent::query()->orderByDesc('created_at');
            if ($request->get('status') === 'active') {
                $data->where('is_active', true);
            } elseif ($request->get('status') === 'inactive') {
                $data->where('is_active', false);
            }
            return DataTables::of($data)
                ->addColumn('status_badge', fn($row) => $row->is_active
                    ? "<span class='label label-success'>نشط</span>"
                    : "<span class='label label-default'>غير نشط</span>")
                ->addColumn('attendance_count', fn($row) => $row->attendance()->count())
                ->addColumn('action', fn($row) =>
                    "<a href='" . route('admin.checkin-events.attendance', $row->id) . "' class='btn btn-xs btn-default' style='margin-right:4px'><i class='fa fa-users'></i> الحضور</a>" .
                    "<a href='" . route('admin.checkin-events.toggle-active', $row->id) . "' class='btn btn-xs " . ($row->is_active ? 'btn-warning' : 'btn-success') . "' style='margin-right:4px'>" .
                    ($row->is_active ? 'إيقاف' : 'تفعيل') . "</a>" .
                    "<a href='" . route('admin.checkin-events.edit', $row->id) . "' class='btn btn-xs btn-info' style='margin-right:4px'><i class='fa fa-edit'></i></a>" .
                    "<a data-toggle='modal' class='delete-link btn btn-xs btn-danger' href='#deleteModal' id='" .
                    route('admin.checkin-events.destroy', $row->id) . "'><i class='fa fa-trash'></i></a>")
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        $activeStatus = $request->get('status');
        $filterButtons = '<div style="display:inline-block;">' .
            '<a href="' . route('admin.checkin-events.index') . '" class="btn btn-sm ' .
            (!$activeStatus ? 'btn-primary' : 'btn-default') . '" style="margin-right:6px">الكل</a>' .
            '<a href="' . route('admin.checkin-events.index', ['status' => 'active']) . '" class="btn btn-sm ' .
            ($activeStatus === 'active' ? 'btn-primary' : 'btn-default') . '" style="margin-right:6px">نشط</a>' .
            '<a href="' . route('admin.checkin-events.index', ['status' => 'inactive']) . '" class="btn btn-sm ' .
            ($activeStatus === 'inactive' ? 'btn-primary' : 'btn-default') . '" style="margin-right:6px">غير نشط</a>' .
            '</div>';

        return view('components.table_ajax')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'مناسبات تسجيل الحضور',
            'table_title' => '',
            'slug'        => 'checkin-events',
            'custom_btn'  =>
                "<a href='" . route('admin.checkin-events.create') . "' class='btn btn-primary'>إضافة مناسبة</a>",
            'custom_btn1' => $filterButtons,
            'headers'     => ['#', 'الاسم', 'المكان', 'التاريخ', 'عدد الحضور', 'الحالة', 'Action'],
            'action'      => route('admin.checkin-events.index'),
            'columns'     => json_encode([
                ['data' => 'id',                'name' => 'id'],
                ['data' => 'name',              'name' => 'name'],
                ['data' => 'location',          'name' => 'location'],
                ['data' => 'event_date',        'name' => 'event_date'],
                ['data' => 'attendance_count',  'name' => 'attendance_count', 'searchable' => false, 'sortable' => false],
                ['data' => 'status_badge',      'name' => 'status_badge', 'searchable' => false, 'sortable' => false],
                ['data' => 'action',            'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    public function toggleActive($id)
    {
        $event = CheckinEvent::findOrFail($id);
        $event->is_active = !$event->is_active;
        $event->save();

        return back()->with('message', $event->is_active
            ? 'تم تفعيل المناسبة بنجاح'
            : 'تم إيقاف المناسبة بنجاح');
    }

    public function create()
    {
        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'إضافة مناسبة',
            'method'      => 'post',
            'form_action' => route('admin.checkin-events.store'),
            'boxes' => [[
                'wrapper-class' => 'col-md-12',
                'class'         => 'box-default',
                'box-header'    => 'معلومات المناسبة',
                'form_fields'   => [
                    $this->drawHtml('small_text', 'الاسم', 'name', null, null, '', 'col-md-12 required'),
                    $this->drawHtml('small_text', 'المكان', 'location', null, null, '', 'col-md-12'),
                    $this->drawHtml('date-time-picker', 'التاريخ', 'event_date', null, null, '', 'col-md-12'),
                    $this->drawHtml('checkbox', 'نشط (يظهر في التطبيق)', 'is_active', true, null, '', 'col-md-12'),
                ],
            ]],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:191',
            'location'   => 'nullable|string|max:191',
            'event_date' => 'nullable|date',
        ]);

        CheckinEvent::create([
            'name'       => $request->name,
            'location'   => $request->location ?: null,
            'event_date' => $request->event_date ?: null,
            'is_active'  => $request->has('is_active'),
        ]);

        return redirect()->route('admin.checkin-events.index')
            ->with('message', 'تمت إضافة المناسبة بنجاح');
    }

    public function edit($id)
    {
        $event = CheckinEvent::findOrFail($id);

        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'تعديل مناسبة',
            'method'      => 'update',
            'form_action' => route('admin.checkin-events.update', $id),
            'boxes' => [[
                'wrapper-class' => 'col-md-12',
                'class'         => 'box-default',
                'box-header'    => 'معلومات المناسبة',
                'form_fields'   => [
                    $this->drawHtml('small_text', 'الاسم', 'name', $event->name, null, '', 'col-md-12 required'),
                    $this->drawHtml('small_text', 'المكان', 'location', $event->location, null, '', 'col-md-12'),
                    $this->drawHtml('date-time-picker', 'التاريخ', 'event_date', $event->event_date?->format('Y-m-d H:i'), null, '', 'col-md-12'),
                    $this->drawHtml('checkbox', 'نشط (يظهر في التطبيق)', 'is_active', $event->is_active, null, '', 'col-md-12'),
                ],
            ]],
        ]);
    }

    public function update(Request $request, $id)
    {
        $event = CheckinEvent::findOrFail($id);

        $request->validate([
            'name'       => 'required|string|max:191',
            'location'   => 'nullable|string|max:191',
            'event_date' => 'nullable|date',
        ]);

        $event->update([
            'name'       => $request->name,
            'location'   => $request->location ?: null,
            'event_date' => $request->event_date ?: null,
            'is_active'  => $request->has('is_active'),
        ]);

        return redirect()->route('admin.checkin-events.index')
            ->with('message', 'تم تعديل المناسبة بنجاح');
    }

    public function destroy($id)
    {
        CheckinEvent::findOrFail($id)->delete();
        return back()->with('message', 'تم حذف المناسبة بنجاح');
    }

    public function attendance(Request $request, $id)
    {
        $event = CheckinEvent::findOrFail($id);

        if ($request->ajax()) {
            $data = $event->attendance()->with(['scannedBy', 'addedByAdmin'])->orderByDesc('checked_in_at');
            return DataTables::of($data)
                ->addColumn('member_name', function ($row) {
                    return \DB::table('fpm_users')->where('MemberId', $row->member_id)->value('UserFullName');
                })
                ->addColumn('scanned_by_name', function ($row) {
                    if ($row->addedByAdmin) {
                        return optional($row->addedByAdmin)->name . ' (يدوياً)';
                    }
                    return optional($row->scannedBy)->name;
                })
                ->make(true);
        }

        return view('cms.checkin_events.attendance')->with([
            'layout' => 'layouts.cms',
            'event'  => $event,
        ]);
    }

    public function searchMembers(Request $request, $id)
    {
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $results = \DB::table('fpm_users')
            ->where(function ($query) use ($q) {
                $query->where('UserFullName', 'like', "%{$q}%")
                    ->orWhereRaw("TRIM(LEADING '+' FROM MobileNumber) LIKE ?", ['%' . ltrim($q, '+') . '%']);
                if (ctype_digit($q)) {
                    $query->orWhere('MemberId', $q);
                }
            })
            ->limit(10)
            ->get(['MemberId', 'UserFullName', 'MobileNumber']);

        return response()->json($results->map(fn($m) => [
            'member_id' => $m->MemberId,
            'name' => $m->UserFullName,
            'phone' => $m->MobileNumber,
        ]));
    }

    public function storeAttendance(Request $request, $id)
    {
        $event = CheckinEvent::findOrFail($id);

        $request->validate([
            'member_id' => 'required|integer|exists:fpm_users,MemberId',
        ]);

        $memberName = \DB::table('fpm_users')->where('MemberId', $request->member_id)->value('UserFullName');

        $existing = $event->attendance()->where('member_id', $request->member_id)->first();
        if ($existing) {
            $message = $memberName . ' مسجل حضوره مسبقاً لهذه المناسبة.';
            if ($request->ajax()) {
                return response()->json(['message' => $message], 422);
            }
            return back()->with('message', $message);
        }

        \App\V2\EventAttendance::create([
            'event_id' => $event->id,
            'member_id' => $request->member_id,
            'added_by_admin_id' => auth('admin')->id(),
            'checked_in_at' => now(),
        ]);

        $message = 'تمت إضافة ' . $memberName . ' الى لائحة الحضور بنجاح';

        if ($request->ajax()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('admin.checkin-events.attendance', $event->id)
            ->with('message', $message);
    }
}
